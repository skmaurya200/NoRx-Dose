<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\Product;
use App\Services\Catalogue\SearchService;
use App\Support\DefaultImage;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ChatProductSearchService
{
    public const PER_PAGE = 5;

    public function __construct(private readonly SearchService $search) {}

    /** @param array<string, mixed> $context */
    public function paginate(array $context, int $page = 1): LengthAwarePaginator
    {
        $term = trim($context['query'] ?? '');

        // A shortlist the retrieval index produced. It replaces the keyword
        // query rather than narrowing it: the whole point is that these
        // products matched a meaning the words never mentioned.
        $ids = array_values(array_filter(array_map('intval', $context['ids'] ?? [])));

        // Products the visitor has already been shown. Applied to both branches
        // below, because "show me more" has to mean more whichever way the
        // products were found.
        $exclude = array_values(array_filter(array_map('intval', $context['exclude_ids'] ?? [])));

        if ($ids !== []) {
            $ids = $exclude === [] ? $ids : array_values(array_diff($ids, $exclude));

            if ($ids === []) {
                // Everything the meaning search found has been seen already.
                return $this->orderedByShortlist([0])->whereRaw('1 = 0')
                    ->paginate(self::PER_PAGE, ['*'], 'page', $page);
            }

            return $this->orderedByShortlist($ids)->paginate(self::PER_PAGE, ['*'], 'page', $page);
        }

        $query = $term === ''
            ? Product::query()->published()->with('category:id,name,slug')->orderBy('name')
            : $this->search->productQuery($term);

        if (! empty($context['brand'])) {
            $query->where('brand', 'like', SqlLike::contains($context['brand']));
        }
        if (! empty($context['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category
                ->where('name', 'like', SqlLike::contains($context['category'])));
        }
        foreach (['min_price' => '>=', 'max_price' => '<='] as $field => $operator) {
            if (isset($context[$field])) {
                $query->where('price', $operator, $context[$field]);
            }
        }
        if (isset($context['min_price']) || isset($context['max_price']) || ! empty($context['currency'])) {
            $query->where('currency', $context['currency'] ?? config('shop.currency'));
        }
        if (($context['in_stock'] ?? null) === true) {
            $query->where(fn (Builder $stock) => $stock->where('track_inventory', false)
                ->orWhere('allow_backorder', true)->orWhere('stock_quantity', '>', 0));
        }
        if (($context['in_stock'] ?? null) === false) {
            $query->where('track_inventory', true)->where('allow_backorder', false)->where('stock_quantity', '<=', 0);
        }

        if ($exclude !== []) {
            $query->whereIntegerNotInRaw('id', $exclude);
        }

        return $query->orderBy('id')->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * The shortlist, kept in the order retrieval ranked it.
     *
     * A CASE rather than MySQL's FIELD(), so the same query runs on SQLite -
     * and still through published(), because a shortlist is not a licence to
     * show a draft.
     *
     * @param  list<int>  $ids
     */
    private function orderedByShortlist(array $ids): Builder
    {
        $order = 'CASE id';

        foreach (array_values($ids) as $position => $id) {
            $order .= ' WHEN '.(int) $id.' THEN '.$position;
        }

        $order .= ' ELSE '.count($ids).' END';

        return Product::query()
            ->published()
            ->with('category:id,name,slug')
            ->whereIn('id', $ids)
            ->orderByRaw($order)
            ->orderBy('id');
    }

    /**
     * Persists one page of results beside the message that produced them.
     *
     * A snapshot, not a live join: the list a visitor was shown has to stay
     * the list they were shown, even after the catalogue moves on.
     */
    public function savePage(ChatMessage $message, LengthAwarePaginator $products): void
    {
        foreach ($products->items() as $index => $product) {
            $message->productResults()->create([
                'product_id' => $product->id,
                'position' => ($products->currentPage() - 1) * self::PER_PAGE + $index + 1,
                'snapshot' => $this->describe($product),
            ]);
        }

        $metadata = $message->metadata;
        $metadata['pages'][$products->currentPage()] = $this->pagination($products);
        $message->update(['metadata' => $metadata]);
    }

    /** @return array<string, mixed> */
    public function describe(Product $product): array
    {
        return [
            'name' => $product->name,
            'category' => $product->category?->name,
            'brand' => $product->brand,
            'price' => $product->price,
            'price_formatted' => $product->priceFormatted(),
            'compare_at_price' => $product->discountPercent() !== null ? $product->compare_at_price : null,
            'compare_at_formatted' => $product->discountPercent() !== null ? $product->compareAtFormatted() : null,
            'currency' => $product->currency,
            'image' => $product->thumbnailUrl() ?? DefaultImage::product(),
            'availability' => $product->isOutOfStock() ? 'Out of stock' : 'Available to order',
            'url' => route('product', $product->slug),
        ];
    }

    /** @return array{current_page: int, per_page: int, last_page: int, has_more: bool} */
    public function pagination(LengthAwarePaginator $products): array
    {
        return [
            'current_page' => $products->currentPage(),
            'per_page' => self::PER_PAGE,
            'last_page' => $products->lastPage(),
            'has_more' => $products->hasMorePages(),
        ];
    }
}
