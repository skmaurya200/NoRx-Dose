<?php

namespace App\Services\Chat;

use App\Models\ChatSession;

/**
 * What the conversation is currently about, written down rather than remembered.
 *
 * The assistant used to work out "the second one" by looking at whichever
 * message last happened to carry product cards. That is a record of what was
 * drawn, not a record of what the conversation is about, and it could not
 * answer the questions people actually ask next: show me more, exclude what I
 * have seen, compare the first and last. Those need the search that produced
 * the list, in order, along with everything already shown.
 *
 * So the session's existing context column carries a shape instead of a
 * breadcrumb. It is deliberately small - ids, a query and its filters - because
 * everything else (price, stock, name) is read from the catalogue when it is
 * needed, and a copy kept here would be a copy going stale.
 */
class ChatContextService
{
    /**
     * The state after showing a page of search results.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<int>  $resultIds  what this answer showed, in the order shown
     * @param  array<string, mixed>  $previous  the state being replaced
     * @return array<string, mixed>
     */
    public function search(
        string $query,
        array $filters,
        array $resultIds,
        string $messageUuid,
        int $page = 1,
        bool $hasMore = false,
        array $previous = [],
    ): array {
        // Anything shown for the same search stays on the seen list, so "more
        // options I have not seen" can mean it. A different search starts the
        // list again - they have not seen these.
        $continuing = ($previous['query'] ?? null) === $query;

        return [
            'type' => 'search',
            'message_uuid' => $messageUuid,
            'query' => $query,
            'filters' => $filters,
            'result_ids' => array_values($resultIds),
            'seen_ids' => $this->merge($continuing ? ($previous['seen_ids'] ?? []) : [], $resultIds),
            'selected_id' => null,
            'page' => $page,
            'has_more' => $hasMore,
        ];
    }

    /**
     * The state after settling on one product.
     *
     * The set it came out of is carried forward, because "and the first one?"
     * after picking the cheapest still means the first of the list.
     *
     * @param  array<string, mixed>  $previous
     * @return array<string, mixed>
     */
    public function product(int $productId, array $previous = [], int $offset = 1): array
    {
        return [
            'type' => 'product',
            'product_id' => $productId,
            'ref' => 'product:'.$productId,
            'offset' => $offset,
            'query' => $previous['query'] ?? null,
            'filters' => $previous['filters'] ?? [],
            'result_ids' => array_values($previous['result_ids'] ?? []),
            'seen_ids' => $this->merge($previous['seen_ids'] ?? [], [$productId]),
            'selected_id' => $productId,
            'message_uuid' => $previous['message_uuid'] ?? null,
        ];
    }

    /**
     * The list the visitor is looking at, in the order they saw it.
     *
     * @return list<int>
     */
    public function resultIds(ChatSession $session): array
    {
        return array_values(array_filter(array_map(
            'intval',
            $session->context['result_ids'] ?? [],
        )));
    }

    /**
     * Everything shown for the current search, across its pages.
     *
     * @return list<int>
     */
    public function seenIds(ChatSession $session): array
    {
        return array_values(array_filter(array_map(
            'intval',
            $session->context['seen_ids'] ?? [],
        )));
    }

    /**
     * The one product under discussion, if the conversation has narrowed to one.
     */
    public function selectedId(ChatSession $session): ?int
    {
        $id = $session->context['selected_id'] ?? $session->context['product_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    public function query(ChatSession $session): string
    {
        return trim((string) ($session->context['query'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(ChatSession $session): array
    {
        $filters = $session->context['filters'] ?? [];

        return is_array($filters) ? $filters : [];
    }

    /**
     * @param  list<int>  $existing
     * @param  list<int>  $added
     * @return list<int>
     */
    private function merge(array $existing, array $added): array
    {
        return array_values(array_unique(array_map('intval', array_merge($existing, $added))));
    }
}
