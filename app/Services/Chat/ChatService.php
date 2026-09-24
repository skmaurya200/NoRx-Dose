<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The guest side of the chat.
 *
 * Ownership is the thing to be careful with here. A session uuid travels in
 * the URL and is not a secret; what proves a conversation belongs to this
 * browser is the chat_owner value in the server-side session, hashed into
 * owner_hash. Every read and write goes through owned(), so possessing someone
 * else's uuid is worth nothing.
 *
 * Writing order matters too: the visitor's message is committed before any
 * outbound call, and keyed on their request_uuid, so a timeout followed by a
 * retry replays the same answer instead of asking twice and charging twice.
 */
class ChatService
{
    public function __construct(
        private readonly ChatAnswerService $answers,
        private readonly ChatProductSearchService $products,
    ) {}

    public function start(Request $request): ChatSession
    {
        $owner = $request->session()->get('chat_owner');

        if (! is_string($owner) || strlen($owner) !== 64) {
            $owner = Str::random(64);
            $request->session()->put('chat_owner', $owner);
        }

        $mode = config('chat.default_reply_mode') === ChatSession::MODE_MANUAL
            ? ChatSession::MODE_MANUAL
            : ChatSession::MODE_AI;

        return ChatSession::query()->firstOrCreate(
            ['owner_hash' => hash('sha256', $owner)],
            ['session_uuid' => (string) Str::uuid(), 'reply_mode' => $mode],
        );
    }

    public function owned(Request $request, string $uuid): ChatSession
    {
        $owner = $request->session()->get('chat_owner');
        abort_unless(is_string($owner) && Str::isUuid($uuid), 404);

        return ChatSession::query()->where('session_uuid', $uuid)
            ->where('owner_hash', hash('sha256', $owner))->firstOrFail();
    }

    /**
     * @param  array{message: string, request_uuid: string, session_uuid: string}  $data
     * @return array<string, mixed>
     */
    public function send(ChatSession $session, array $data): array
    {
        $user = $session->messages()->firstOrCreate(
            ['request_uuid' => $data['request_uuid']],
            [
                'message_uuid' => (string) Str::uuid(),
                'sender' => 'user',
                'authored_by' => ChatMessage::AUTHOR_GUEST,
                'message' => $data['message'],
            ],
        );

        abort_unless(
            $user->message === $data['message'],
            409,
            'This request identifier was already used for another message.',
        );

        if ($reply = $user->reply()->first()) {
            return $this->envelope($session, $reply) + ['user_message' => $this->describe($user)];
        }

        $this->noteVisitorMessage($session);

        // A person has taken this conversation over. Nothing is generated, and
        // nothing is queued: the panel shows the question and the reply comes
        // back through the same history the widget is already polling.
        if ($session->isManual()) {
            return [
                'session_uuid' => $session->session_uuid,
                'message' => null,
                'products' => [],
                'pagination' => null,
                'requires_contact' => false,
                'awaiting_human' => true,
                'user_message' => $this->describe($user),
            ];
        }

        try {
            $reply = $this->answers->reply($session, $user);
        } catch (Throwable $exception) {
            // The question is already committed, so nothing is lost. What the
            // visitor gets is a route to a person rather than an apology that
            // leads nowhere - and never the exception, which can carry the
            // provider's own error text.
            Log::warning('Chat response failed; preserving question and storing fallback.', [
                'message_uuid' => $user->message_uuid,
                'error_type' => $exception::class,
            ]);

            $reply = $user->reply()->first() ?? $session->messages()->create([
                'message_uuid' => (string) Str::uuid(),
                'reply_to_id' => $user->id,
                'sender' => 'assistant',
                'authored_by' => ChatMessage::AUTHOR_AI,
                'message' => config('chat.failure_message'),
                'message_type' => 'fallback',
                'intent' => $user->intent ?? 'unsupported',
                'metadata' => ['requires_contact' => true, 'awaiting_contact' => true],
            ]);

            // The reply asked for a way to reach them, so the next message has
            // to be read as the answer to that rather than as a new question.
            // The products on screen are kept, so the conversation survives.
            $session->forceFill([
                'context' => array_merge($session->refresh()->context ?? [], ['awaiting_contact_uuid' => $user->message_uuid]),
            ])->save();
        }

        $session->forceFill(['last_message_at' => now()])->save();

        return $this->envelope($session, $reply) + ['user_message' => $this->describe($user)];
    }

    /**
     * A reply typed by an admin, which reaches the visitor through the same
     * history endpoint their widget already reads.
     */
    public function replyAsManager(ChatSession $session, string $text, int $adminId): ChatMessage
    {
        $message = $session->messages()->create([
            'message_uuid' => (string) Str::uuid(),
            'sender' => 'assistant',
            'authored_by' => ChatMessage::AUTHOR_MANAGER,
            'admin_id' => $adminId,
            'message' => $text,
            'message_type' => 'text',
            'metadata' => ['requires_contact' => false],
        ]);

        // Answered: the inbox badge is about questions nobody has handled.
        $session->forceFill(['last_message_at' => now(), 'unread_count' => 0])->save();

        return $message;
    }

    /**
     * Switches a conversation between the model and a person.
     *
     * Taking over says so in the transcript, because a visitor who asked a
     * question and then waits deserves to know somebody is coming.
     */
    public function setReplyMode(ChatSession $session, string $mode): ChatSession
    {
        $mode = in_array($mode, ChatSession::MODES, true) ? $mode : ChatSession::MODE_AI;

        if ($session->reply_mode === $mode) {
            return $session;
        }

        $session->forceFill(['reply_mode' => $mode])->save();

        if ($mode === ChatSession::MODE_MANUAL) {
            $session->messages()->create([
                'message_uuid' => (string) Str::uuid(),
                'sender' => 'assistant',
                'authored_by' => ChatMessage::AUTHOR_MANAGER,
                'message' => config('chat.handover_message'),
                'message_type' => 'system',
                'metadata' => ['requires_contact' => false],
            ]);

            $session->forceFill(['last_message_at' => now()])->save();
        }

        return $session->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function more(ChatSession $session, string $messageUuid, int $pageNumber): array
    {
        $message = $session->messages()->where('message_uuid', $messageUuid)
            ->where('sender', 'assistant')->where('message_type', 'product')->firstOrFail();

        $metadata = $message->metadata;
        $pages = $metadata['pages'] ?? [];

        if (isset($pages[$pageNumber])) {
            return $this->envelope($session, $message, $pageNumber);
        }

        $last = $pages === [] ? null : $pages[max(array_keys($pages))];

        abort_unless(
            $last && $last['has_more'] && $pageNumber === $last['current_page'] + 1,
            422,
            'Load the next available page.',
        );

        // Deliberately no model call: the page is another slice of a query the
        // visitor already ran, and re-classifying it would be paying twice for
        // the same intent.
        $products = $this->products->paginate($metadata['context'], $pageNumber);

        DB::transaction(fn () => $this->products->savePage($message, $products));

        $session->touch();

        return $this->envelope($session, $message, $pageNumber);
    }

    /**
     * One page of the conversation.
     *
     * `before` walks backwards for "load earlier"; `after` walks forwards and
     * is what the open widget polls with while it waits for a person to reply.
     *
     * @return array<string, mixed>
     */
    public function history(ChatSession $session, ?string $before, ?string $after = null): array
    {
        if ($after !== null) {
            return $this->since($session, $after);
        }

        // A conversation nobody has written in opens with the assistant's own
        // welcome and suggestions - generated, never a stored greeting. Nothing
        // is shown if the model is unavailable.
        if ($before === null && ! $session->isManual() && ! $session->messages()->exists()) {
            $this->answers->welcome($session);
        }

        $query = $session->messages()->with([
            'productResults' => fn ($results) => $results->where('position', '<=', ChatProductSearchService::PER_PAGE),
            'contact',
        ])->orderByDesc('id');

        if ($before !== null) {
            $cursor = $session->messages()->where('message_uuid', $before)->firstOrFail();
            $query->where('id', '<', $cursor->id);
        }

        $rows = $query->limit(31)->get();
        $hasMore = $rows->count() > 30;
        $messages = $rows->take(30)->reverse()->values();

        return [
            'session_uuid' => $session->session_uuid,
            'reply_mode' => $session->reply_mode,
            'messages' => $messages->map(fn (ChatMessage $message) => $this->describe($message))->all(),
            'pagination' => ['has_more' => $hasMore, 'before' => $hasMore ? $messages->first()->message_uuid : null],
        ];
    }

    /**
     * Everything after a message the widget already has.
     *
     * Bounded like every other read here: a poll cannot be turned into a way
     * of pulling a whole conversation in one request.
     *
     * @return array<string, mixed>
     */
    private function since(ChatSession $session, string $after): array
    {
        $cursor = $session->messages()->where('message_uuid', $after)->firstOrFail();

        $messages = $session->messages()
            ->with([
                'productResults' => fn ($results) => $results->where('position', '<=', ChatProductSearchService::PER_PAGE),
                'contact',
            ])
            ->where('id', '>', $cursor->id)
            ->orderBy('id')
            ->limit(30)
            ->get();

        return [
            'session_uuid' => $session->session_uuid,
            'reply_mode' => $session->reply_mode,
            'messages' => $messages->map(fn (ChatMessage $message) => $this->describe($message))->all(),
            'pagination' => ['has_more' => false, 'before' => null],
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    public function contact(ChatSession $session, array $data): array
    {
        $message = $session->messages()->where('message_uuid', $data['message_uuid'])
            ->where('sender', 'assistant')->where('message_type', 'fallback')->firstOrFail();

        abort_unless($message->metadata['requires_contact'] ?? false, 422, 'Contact information was not requested.');

        if ($message->contact()->exists()) {
            $confirmation = $session->messages()->where('message_uuid', $message->metadata['confirmation_uuid'])->firstOrFail();
        } else {
            // Saved first, and never sent anywhere: the details go to the team,
            // the model is only told that they did.
            $question = DB::transaction(function () use ($session, $message, $data) {
                $session->contacts()->create([
                    'chat_message_id' => $message->id,
                    'name' => $data['name'], 'email' => mb_strtolower($data['email']),
                    'phone' => $data['phone'], 'message' => $data['message'],
                ]);

                return $session->messages()->create([
                    'message_uuid' => (string) Str::uuid(), 'sender' => 'user',
                    'authored_by' => ChatMessage::AUTHOR_GUEST,
                    'message' => $data['message'], 'message_type' => 'text',
                ]);
            });

            try {
                $confirmation = $this->answers->confirmContact($session, $question);
            } catch (Throwable $exception) {
                Log::warning('Chat contact confirmation could not be generated.', ['error_type' => $exception::class]);

                $confirmation = $session->messages()->create([
                    'message_uuid' => (string) Str::uuid(), 'sender' => 'assistant',
                    'authored_by' => ChatMessage::AUTHOR_AI, 'reply_to_id' => $question->id,
                    'message' => config('chat.failure_message'), 'message_type' => 'text',
                    'metadata' => ['requires_contact' => false],
                ]);
            }

            $metadata = $message->metadata;
            $metadata['confirmation_uuid'] = $confirmation->message_uuid;
            $metadata['contact_question_uuid'] = $question->message_uuid;
            $message->update(['metadata' => $metadata]);
        }

        $message->refresh();
        $this->noteVisitorMessage($session);

        return $this->envelope($session, $confirmation) + [
            'user_message' => $this->describe($session->messages()
                ->where('message_uuid', $message->metadata['contact_question_uuid'])->firstOrFail()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(ChatMessage $message, ?int $page = null): array
    {
        $message->loadMissing([
            'productResults' => fn ($results) => $results
                ->where('position', '>', (($page ?? 1) - 1) * ChatProductSearchService::PER_PAGE)
                ->where('position', '<=', ($page ?? 1) * ChatProductSearchService::PER_PAGE),
            'contact',
        ]);

        $pages = $message->metadata['pages'] ?? [];
        $pagination = $pages === [] ? null : ($pages[$page ?? 1] ?? null);
        $results = $message->productResults;

        if ($page !== null) {
            $results = $results->filter(
                fn ($result) => $result->position > ($page - 1) * ChatProductSearchService::PER_PAGE
                    && $result->position <= $page * ChatProductSearchService::PER_PAGE,
            );
        }

        return [
            'id' => $message->message_uuid,
            'sender' => $message->sender,
            // What the widget labels the bubble with: a person's reply says so
            // rather than being passed off as the assistant.
            'author' => $message->authored_by ?? ChatMessage::AUTHOR_AI,
            'text' => $message->message,
            'type' => $message->message_type,
            'intent' => $message->intent,
            'created_at' => $message->created_at->toIso8601String(),
            'products' => $results->pluck('snapshot')->values()->all(),
            'pagination' => $pagination,

            // How the shop does something, in its own words rather than a
            // paraphrase of them - see ChatAnswerService::guideAnswer().
            'steps' => array_values($message->metadata['steps'] ?? []),
            'link' => $message->metadata['link'] ?? null,

            // One-tap follow-ups, so an answer ends with somewhere to go.
            'suggestions' => array_values($message->metadata['suggestions'] ?? []),

            // Kept for the panel and for API clients. The widget no longer
            // draws a form from it - the assistant asks in words, and the next
            // message is read for an address or a number.
            'requires_contact' => ($message->metadata['requires_contact'] ?? false) && $message->contact === null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(ChatSession $session, ChatMessage $message, ?int $page = null): array
    {
        $description = $this->describe($message, $page);

        return [
            'session_uuid' => $session->session_uuid,
            'reply_mode' => $session->reply_mode,
            'message' => $description,
            'products' => $description['products'],
            'pagination' => $description['pagination'],
            'requires_contact' => $description['requires_contact'],
            'awaiting_human' => false,
        ];
    }

    /**
     * Counters the panel's inbox reads. Written with an atomic increment
     * rather than a read-modify-write, because two browser tabs are two
     * concurrent requests.
     */
    private function noteVisitorMessage(ChatSession $session): void
    {
        $session->newQuery()->whereKey($session->getKey())->update([
            'unread_count' => DB::raw('unread_count + 1'),
            'last_message_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
