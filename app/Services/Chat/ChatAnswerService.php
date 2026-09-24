<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Product;
use App\Support\Chat\ContactDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Turns one visitor message into one reply the model wrote.
 *
 * There is no stored answer anywhere on this path - no greeting, no template,
 * no canned suggestion, no regex that answers on the model's behalf. Every
 * reply, including a hello, an off-topic question and "which one is cheapest",
 * is written by the model from what it fetched through ChatToolService and
 * from the products the visitor currently has on screen.
 *
 * What this class still decides is whether a reply may be shown. The model
 * writes; the application checks: a price, a product name, an email address
 * or a link nobody returned is refused, the model is told why and asked once
 * more, and a reply that still fails is never printed.
 *
 * Two things stay on this side of the provider by design. A visitor's own
 * email address or phone number is saved here and never sent anywhere. And a
 * conversation a person has taken over gets no reply at all - ChatService
 * stops before this class is reached.
 */
class ChatAnswerService
{
    public function __construct(
        private readonly GeminiService $gemini,
        private readonly ChatToolService $tools,
        private readonly ChatKnowledgeService $knowledge,
        private readonly ChatContextService $state,
        private readonly ChatProductSearchService $products,
    ) {}

    /**
     * @return ChatMessage the stored assistant reply
     */
    public function reply(ChatSession $session, ChatMessage $user): ChatMessage
    {
        $note = null;

        /* A visitor who was asked for a way to be reached and typed one: the
           details are saved here, before anything leaves this machine, and
           the model is only told that it happened. */
        if ($this->isAwaitingContact($session) && ContactDetails::has((string) $user->message)) {
            $this->saveContact($session, $user);

            $note = 'The visitor has just shared their contact details in this message (removed for privacy). '
                .'They have been saved and passed to the store team.';
        }

        return $this->respond($session, $user, (string) $user->message, $note);
    }

    /**
     * The first message of a conversation nobody has written in yet - the
     * model's own welcome, with its own suggestions. Null when the model is
     * unavailable: an empty chat is better than a canned greeting.
     */
    public function welcome(ChatSession $session): ?ChatMessage
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        try {
            return $this->respond($session, null, null, 'The visitor has just opened the chat and has not written '
                .'anything yet. Welcome them to the store and suggest what they could ask. Use getStoreInformation '
                .'if you want to mention anything specific about the store.');
        } catch (Throwable $exception) {
            Log::warning('Chat welcome could not be generated.', ['error_type' => $exception::class]);

            return null;
        }
    }

    /**
     * The reply to a question sent through the contact endpoint, whose details
     * were saved before this is called.
     */
    public function confirmContact(ChatSession $session, ChatMessage $question): ChatMessage
    {
        return $this->respond($session, $question, (string) $question->message, 'The visitor sent this question '
            .'together with their name and contact details (not shown to you), and it has been passed to the '
            .'store team. Confirm that, and answer the question too if the store data lets you.');
    }

    /**
     * @throws RuntimeException when no reply that could be shown came back
     */
    private function respond(ChatSession $session, ?ChatMessage $user, ?string $question, ?string $note): ChatMessage
    {
        $onScreen = $this->productsOnScreen($session);

        $payload = array_filter([
            'question' => $question,
            'note' => $note,
            'products_on_screen' => $onScreen,
            'earlier_turns' => $this->history($session, $user),
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');

        $tokens = 0;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $result = $this->gemini->assist($payload, $this->tools->declarations(), fn (string $name, array $arguments) => $this->runTool($name, $arguments));
            $tokens += (int) $result['tokens_used'];

            $reply = $this->normalise($result);
            $problem = $this->problemWith($reply, $result['calls'], $onScreen, (string) $question);

            if ($problem === null) {
                return $this->store($session, $user, $reply, $result['calls'], $onScreen, $result['ai_model'], $tokens);
            }

            Log::warning('Chat reply refused before it was shown.', ['reason' => $problem]);

            // Told exactly why, and asked once more - in its own words again.
            $payload['correction'] = 'Your previous reply was not shown to the visitor because '.$problem
                .'. Write a new reply that uses only what the functions return.';
        }

        throw new RuntimeException('No chat reply passed its checks.');
    }

    /**
     * Runs one of the store's functions for the model.
     *
     * A database fault is handed back as data rather than thrown, so the model
     * tells the visitor the store could not look it up - in its own words -
     * instead of "we do not have that", which would be untrue.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(string $name, array $arguments): array
    {
        try {
            return $this->tools->call($name, $arguments);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Chat assistant function failed.', ['function' => $name, 'error_type' => $exception::class]);

            return [
                'found' => false,
                'error' => 'The store could not load this information just now. It is a temporary fault, '
                    .'not a sign that the product or information does not exist.',
            ];
        }
    }

    /**
     * The model's reply as this application stores it.
     *
     * @param  array<string, mixed>  $result
     * @return array{answer: string, suggestions: list<string>, product_ids: list<int>, needs_contact: bool, intent: string}
     */
    private function normalise(array $result): array
    {
        $reply = is_array($result['reply']) ? $result['reply'] : ['answer' => $result['text']];

        $intent = is_string($reply['intent'] ?? null) && in_array($reply['intent'], GeminiService::INTENTS, true)
            ? $reply['intent']
            : 'general_question';

        return [
            'answer' => trim(is_string($reply['answer'] ?? null) ? $reply['answer'] : ''),
            'suggestions' => $this->suggestions($reply['suggestions'] ?? []),
            'product_ids' => array_values(array_unique(array_filter(
                array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, (array) ($reply['product_ids'] ?? [])),
            ))),
            'needs_contact' => ($reply['needs_contact'] ?? false) === true,
            'intent' => $intent,
        ];
    }

    /**
     * Why a reply may not be shown, or null when it may.
     *
     * @param  array{answer: string, suggestions: list<string>, product_ids: list<int>, needs_contact: bool, intent: string}  $reply
     * @param  list<array<string, mixed>>  $calls
     * @param  list<array<string, mixed>>  $onScreen
     */
    private function problemWith(array $reply, array $calls, array $onScreen, string $question): ?string
    {
        $answer = $reply['answer'];

        if ($answer === '') {
            return 'it was empty';
        }

        /* Markup, a query, or this application's own internals in a chat
           bubble is an attack that worked or a model that lost the thread. */
        if (! $this->knowledge->readsAsProse($answer)) {
            return 'it contained markup, code or internal details';
        }

        $evidence = trim($this->tools->evidence($calls).' '.$this->tools->evidence([['result' => $onScreen]]));

        if (! $this->knowledge->quotesOnlyKnownFigures($answer, $evidence.' '.$question)) {
            return 'it stated a price, number or duration that no function returned';
        }

        if (! $this->knowledge->mentionsOnlyKnownContacts($answer, $evidence)) {
            return 'it gave an email address or link that no function returned';
        }

        if ($calls === [] && $onScreen === []) {
            // Nothing was fetched, so this can only be conversation or general
            // knowledge - which must not speak for the store.
            return $this->knowledge->claimsShopFact($answer)
                ? 'it described the store without fetching any store data'
                : null;
        }

        if (! $this->knowledge->namesOnlyKnownThings($answer, $evidence.' '.$question)) {
            return 'it named a product, brand or category that no function returned';
        }

        /* The functions found nothing, so a positive claim about the store has
           nothing behind it. "We do not stock melatonin" still stands - the
           check reads negation - while "we also stock melatonin" does not. */
        if (! $this->tools->foundAnything($calls) && $onScreen === [] && $this->knowledge->claimsShopFact($answer)) {
            return 'it claimed something about the store that the functions did not find';
        }

        return null;
    }

    /**
     * @param  array{answer: string, suggestions: list<string>, product_ids: list<int>, needs_contact: bool, intent: string}  $reply
     * @param  list<array<string, mixed>>  $calls
     * @param  list<array<string, mixed>>  $onScreen
     */
    private function store(
        ChatSession $session,
        ?ChatMessage $user,
        array $reply,
        array $calls,
        array $onScreen,
        string $model,
        int $tokens,
    ): ChatMessage {
        $user?->update(['intent' => $reply['intent']]);

        // Only products this turn actually put in front of the model - a card
        // is a claim that this is what the reply is about.
        $allowed = collect($calls)
            ->flatMap(fn (array $call): array => $this->tools->productIds($call['result'] ?? []))
            ->merge(array_column($onScreen, 'id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $ids = array_values(array_intersect($reply['product_ids'], $allowed));
        $search = $this->searchBehind($calls, $ids);
        $previous = $session->context ?? [];

        $attributes = [
            'message_uuid' => (string) Str::uuid(),
            'reply_to_id' => $user?->id,
            'sender' => 'assistant',
            'authored_by' => ChatMessage::AUTHOR_AI,
            'intent' => $reply['intent'],
            'message' => $reply['answer'],
            'ai_model' => $model,
            'tokens_used' => $tokens,
        ];

        $metadata = array_filter([
            'pages' => [],
            'requires_contact' => $reply['needs_contact'],
            'awaiting_contact' => $reply['needs_contact'] ?: null,
            'suggestions' => $reply['suggestions'],
        ], static fn (mixed $value): bool => $value !== null);

        if ($search !== null) {
            /* The reply is about the whole first page of a search, so it is
               replayed through the ordinary paging machinery: "Show more
               products" then means the next page of what was asked for. */
            $context = $this->tools->searchContext($search['arguments']);
            $page = $this->products->paginate($context, 1);

            $message = DB::transaction(function () use ($session, $attributes, $metadata, $context, $page) {
                $message = $session->messages()->create($attributes + [
                    'message_type' => 'product',
                    'metadata' => $metadata + ['context' => $context],
                ]);

                $this->products->savePage($message, $page);

                return $message;
            });

            $next = $this->state->search(
                (string) ($context['query'] ?? ''),
                array_diff_key($context, array_flip(['query', 'ids', 'exclude_ids'])),
                array_map(static fn (Product $product): int => $product->id, $page->items()),
                $message->message_uuid,
                1,
                $page->hasMorePages(),
                $previous,
            );
        } else {
            $cards = $ids === [] ? collect() : Product::query()
                ->published()
                ->with('category:id,name,slug')
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            $ids = array_values(array_filter($ids, fn (int $id): bool => $cards->has($id)));

            $message = DB::transaction(function () use ($session, $attributes, $metadata, $ids, $cards, $reply) {
                $message = $session->messages()->create($attributes + [
                    'message_type' => $ids !== [] ? 'product' : ($reply['needs_contact'] ? 'fallback' : 'text'),
                    'metadata' => $metadata,
                ]);

                foreach ($ids as $position => $id) {
                    $message->productResults()->create([
                        'product_id' => $id,
                        'position' => $position + 1,
                        'snapshot' => $this->products->describe($cards->get($id)),
                    ]);
                }

                return $message;
            });

            // What the conversation is about now. A reply that showed nothing
            // leaves the last set standing, so "which one is cheapest?" still
            // has something to mean after a question about delivery.
            $next = match (true) {
                count($ids) === 1 => $this->state->product($ids[0], $previous),
                count($ids) > 1 => $this->state->search(
                    (string) ($previous['query'] ?? ''),
                    (array) ($previous['filters'] ?? []),
                    $ids,
                    $message->message_uuid,
                    1,
                    false,
                    $previous,
                ),
                default => $previous,
            };
        }

        // Asked for a way to reach the visitor: the next message is read for
        // one before anything else happens. Cleared otherwise.
        unset($next['awaiting_contact_uuid']);

        if ($reply['needs_contact']) {
            $next['awaiting_contact_uuid'] = $user?->message_uuid ?? $message->message_uuid;
        }

        $this->remember($session, $next);

        return $message;
    }

    /**
     * The search a reply is about, when it is about that search's whole first
     * page - which is when "Show more products" should page through it.
     *
     * @param  list<array<string, mixed>>  $calls
     * @param  list<int>  $ids
     * @return array<string, mixed>|null
     */
    private function searchBehind(array $calls, array $ids): ?array
    {
        $search = collect($calls)->last(fn (array $call): bool => $call['name'] === 'searchProducts');

        if ($search === null || count($ids) < 2 || isset($search['result']['note'])) {
            return null;
        }

        $returned = $this->tools->productIds($search['result'] ?? []);

        sort($returned);
        $chosen = $ids;
        sort($chosen);

        return $returned === $chosen ? $search : null;
    }

    /**
     * The products the visitor is looking at, read live from the catalogue in
     * the order they were shown, so "the second one" and "the cheapest" can be
     * worked out by the model from real prices.
     *
     * @return list<array<string, mixed>>
     */
    private function productsOnScreen(ChatSession $session): array
    {
        $ids = $this->state->resultIds($session);
        $selected = $this->state->selectedId($session);

        if ($selected !== null && ! in_array($selected, $ids, true)) {
            $ids[] = $selected;
        }

        if ($ids === []) {
            return [];
        }

        $products = Product::query()
            ->published()
            ->with('category:id,name')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->filter(fn (int $id): bool => $products->has($id))
            ->values()
            ->map(function (int $id, int $index) use ($products, $selected): array {
                $product = $products->get($id);

                return [
                    'position' => $index + 1,
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'price' => (float) $product->price,
                    'price_formatted' => $product->priceFormatted(),
                    'available' => ! $product->isOutOfStock(),
                    'is_the_one_being_discussed' => $selected === $product->id,
                ];
            })
            ->all();
    }

    /**
     * The model's follow-up suggestions, cleaned for display as buttons.
     *
     * @return list<string>
     */
    private function suggestions(mixed $suggestions): array
    {
        return collect(is_array($suggestions) ? $suggestions : [])
            ->filter(fn (mixed $text): bool => is_string($text))
            ->map(fn (string $text): string => trim((string) preg_replace('/\s+/u', ' ', strip_tags($text))))
            ->filter(fn (string $text): bool => mb_strlen($text) >= 2 && mb_strlen($text) <= 90
                && preg_match('#https?://|@|[<>{}\[\]]#u', $text) !== 1)
            ->unique(fn (string $text): string => mb_strtolower($text))
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * Earlier turns, oldest first, for reference - the message being answered
     * is sent separately as the question.
     *
     * @return list<array{role: string, text: string}>
     */
    private function history(ChatSession $session, ?ChatMessage $user): array
    {
        $turns = max(0, (int) config('chat.answer.history_turns', 8));

        if ($turns === 0) {
            return [];
        }

        return $session->messages()
            ->where('message_type', '!=', 'system')
            ->when($user !== null, fn ($query) => $query->where('id', '<', $user->id))
            ->orderByDesc('id')
            ->limit($turns)
            ->get(['sender', 'message'])
            ->reverse()
            ->map(fn (ChatMessage $message): array => [
                'role' => $message->sender === 'user' ? 'user' : 'assistant',
                'text' => (string) $message->message,
            ])
            ->values()
            ->all();
    }

    /**
     * Whether the assistant last asked for a way to reach this visitor - by
     * its own choice, or because the provider failed (ChatService).
     */
    private function isAwaitingContact(ChatSession $session): bool
    {
        return isset($session->context['awaiting_contact_uuid'])
            || ($session->context['type'] ?? null) === 'contact';
    }

    /**
     * Stores what the visitor offered, against the question that prompted it.
     */
    private function saveContact(ChatSession $session, ChatMessage $user): void
    {
        $details = ContactDetails::find((string) $user->message);
        $context = $session->context ?? [];

        $asked = $session->messages()
            ->where('message_uuid', $context['awaiting_contact_uuid'] ?? $context['asked_uuid'] ?? '')
            ->first();

        DB::transaction(fn () => $session->contacts()->create([
            'chat_message_id' => $user->id,
            'email' => $details['email'],
            'phone' => $details['phone'],
            // The question they could not get an answer to is the reason
            // support is being handed this, so it goes with it.
            'message' => $asked?->sender === 'user' ? $asked->message : $user->message,
        ]));

        unset($context['awaiting_contact_uuid'], $context['asked_uuid']);

        if (($context['type'] ?? null) === 'contact') {
            $context = [];
        }

        $this->remember($session, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function remember(ChatSession $session, array $context): void
    {
        $session->forceFill(['context' => $context === [] ? null : $context])->save();
    }
}
