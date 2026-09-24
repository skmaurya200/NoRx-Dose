<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The panel's view of the conversations.
 *
 * Read-only except for marking a conversation read. Everything that changes a
 * conversation - replying, taking it over - goes through ChatService, so the
 * guest side and the panel side write through one implementation and cannot
 * drift apart on what a reply is.
 */
class ChatInboxService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return ChatSession::query()
            ->with(['latestMessage', 'latestContact'])
            ->withCount('messages')
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters) {
                $like = SqlLike::contains((string) $filters['search']);

                // The uuid is how a conversation is referred to anywhere else,
                // and the contact details are how an operator remembers who
                // this was.
                $query->where(fn (Builder $match) => $match
                    ->where('session_uuid', 'like', $like)
                    ->orWhereHas('contacts', fn (Builder $contact) => $contact
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like))
                    ->orWhereHas('messages', fn (Builder $message) => $message
                        ->where('message', 'like', $like)));
            })
            ->when(($filters['mode'] ?? '') !== '', fn (Builder $query) => $query
                ->where('reply_mode', $filters['mode'] === ChatSession::MODE_MANUAL
                    ? ChatSession::MODE_MANUAL
                    : ChatSession::MODE_AI))
            ->when(($filters['status'] ?? '') === 'unread', fn (Builder $query) => $query->where('unread_count', '>', 0))
            ->when(($filters['status'] ?? '') === 'contacted', fn (Builder $query) => $query->has('contacts'))
            // Newest activity first: an inbox is read from the top.
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * The whole transcript of one conversation, oldest first.
     *
     * Not paginated: an operator about to answer somebody needs the context,
     * and a guest conversation is bounded by the rate limits long before it
     * becomes large enough to matter.
     *
     * @return Collection<int, ChatMessage>
     */
    public function transcript(ChatSession $session): Collection
    {
        return $session->messages()
            ->with(['productResults', 'contact', 'admin:id,name'])
            ->orderBy('id')
            ->limit(500)
            ->get();
    }

    /**
     * Messages the panel has not drawn yet, for the open conversation screen.
     *
     * @return Collection<int, ChatMessage>
     */
    public function since(ChatSession $session, ?string $afterUuid): Collection
    {
        $query = $session->messages()->with(['productResults', 'admin:id,name'])->orderBy('id');

        if ($afterUuid !== null) {
            $cursor = $session->messages()->where('message_uuid', $afterUuid)->first();

            if ($cursor === null) {
                return $session->messages()->newCollection();
            }

            $query->where('id', '>', $cursor->id);
        }

        return $query->limit(50)->get();
    }

    /**
     * Opening a conversation is what clears its badge.
     */
    public function markRead(ChatSession $session): void
    {
        if ($session->unread_count > 0) {
            $session->forceFill(['unread_count' => 0])->save();
        }
    }

    /**
     * Counters for the cards above the list.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'total' => ChatSession::query()->count(),
            'unread' => ChatSession::query()->where('unread_count', '>', 0)->count(),
            'manual' => ChatSession::query()->where('reply_mode', ChatSession::MODE_MANUAL)->count(),
            'contacts' => ChatSession::query()->has('contacts')->count(),
        ];
    }

    /**
     * What the sidebar badge counts.
     */
    public function unreadCount(): int
    {
        return ChatSession::query()->where('unread_count', '>', 0)->count();
    }

    /**
     * One transcript row, in the shape the panel's script draws.
     *
     * @return array<string, mixed>
     */
    public function describe(ChatMessage $message): array
    {
        return [
            'id' => $message->message_uuid,
            'sender' => $message->sender,
            'author' => $message->authored_by ?? ChatMessage::AUTHOR_AI,
            'author_name' => $message->admin?->name,
            'text' => $message->message,
            'type' => $message->message_type,
            'intent' => $message->intent,
            'ai_model' => $message->ai_model,
            'tokens_used' => $message->tokens_used,
            'created_at' => $message->created_at->toIso8601String(),
            'products' => $message->productResults->pluck('snapshot')->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $requested = (int) ($filters['per_page'] ?? config('admin.catalogue.per_page'));

        return min(max($requested, 1), (int) config('admin.catalogue.max_per_page'));
    }
}
