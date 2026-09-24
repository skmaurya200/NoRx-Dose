<?php

namespace App\Services\Catalogue;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read side of the catalogue, for the public storefront.
 *
 * Kept apart from ProductService (which is the back office's write path)
 * because the two answer different questions: the panel wants everything
 * including drafts and cost prices, the storefront wants only what is live.
 * Every query here goes through Product::published(), so a draft or archived
 * product can never leak onto a public page.
 */
class StorefrontCatalogue
{
    /**
     * Columns the shop's sort dropdown is allowed to order by, keyed by the
     * value the <select> posts. An allow-list, because putting a request value
     * straight into orderBy() is SQL injection.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'default' => ['created_at', 'desc'],
        'popular' => ['rating_count', 'desc'],
        'rating' => ['rating_avg', 'desc'],
        'new' => ['created_at', 'desc'],
        'low' => ['price', 'asc'],
        'high' => ['price', 'desc'],
    ];

    /**
     * Products for the "Customer favourites" rail on the home page.
     *
     * @return Collection<int, Product>
     */
    public function featured(int $limit = 5): Collection
    {
        return $this->base()
            ->orderByDesc('is_featured')
            ->orderByDesc('rating_avg')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * The home page's favourites grid: the best of the catalogue, spread out.
     *
     * Ten products picked by taking the strongest one from each category
     * before taking a second from any of them, so a shop whose best-rated
     * products all sit in one category still shows a customer the range rather
     * than ten variations of the same thing. Once every category has had a
     * turn, the remaining places go to whatever ranked highest overall.
     *
     * @return Collection<int, Product>
     */
    public function featuredSpread(int $limit = 10): Collection
    {
        $candidates = $this->base()
            ->orderByDesc('is_featured')
            ->orderByDesc('rating_avg')
            ->orderByDesc('created_at')
            // A ceiling rather than pagination: the walk below is over a
            // collection, and the whole catalogue is not worth loading to fill
            // ten slots.
            ->limit(max($limit * 4, 40))
            ->get();

        // The first appearance of each category, in the order they ranked.
        $leading = [];

        foreach ($candidates as $product) {
            $category = (int) ($product->category_id ?? 0);

            if (! isset($leading[$category])) {
                $leading[$category] = $product->id;
            }
        }

        // Those take the front places; everything else keeps its own order
        // behind them. sortBy is stable in PHP 8, so "behind them" really is
        // the ranking above rather than an arbitrary shuffle.
        $rank = array_flip(array_slice(array_values($leading), 0, $limit));

        return $candidates
            ->sortBy(fn (Product $product) => $rank[$product->id] ?? PHP_INT_MAX)
            ->take($limit)
            ->values();
    }

    /**
     * Products for the "New arrivals" rail.
     *
     * Prefers products the favourites rail did not already show, so the home
     * page does not print the same card twice on one screen. That is a
     * preference and not a filter: on a small catalogue every product can be a
     * favourite, and an empty "New arrivals" section reads as a broken page,
     * which is worse than an overlap.
     *
     * @param  Collection<int, Product>|null  $exclude
     * @return Collection<int, Product>
     */
    public function latest(int $limit = 5, ?Collection $exclude = null): Collection
    {
        $newest = $this->base()
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit($limit * 2)
            ->get();

        $shown = $exclude?->modelKeys() ?? [];

        $fresh = $newest->reject(fn (Product $product) => in_array($product->id, $shown, true));

        // Top up with the ones that were held back, newest first, rather than
        // leaving the rail short.
        return $fresh->concat($newest->diff($fresh))->take($limit)->values();
    }

    /**
     * The full live catalogue, for the all-products page. That page filters by
     * category in the browser, so it needs every product in one response - the
     * cap is a safety net rather than pagination.
     *
     * @return Collection<int, Product>
     */
    public function all(int $cap = 200): Collection
    {
        return $this->base()
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit($cap)
            ->get();
    }

    /**
     * One page of products for the shop, ordered by the sort dropdown and
     * optionally narrowed to a single category.
     *
     * The narrowing is done in SQL rather than by hiding cards in the browser,
     * so the count, the sort and the pagination all describe the same set - a
     * filtered page 2 means what it says.
     */
    public function paginate(
        ?string $sort,
        ?ProductCategory $category = null,
        int $perPage = 12,
    ): LengthAwarePaginator {
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['default'];

        return $this->base()
            ->when($category !== null, fn (Builder $query) => $query->where('category_id', $category->id))
            ->orderBy($column, $direction)
            // Ties are broken by id so the same product cannot appear on two
            // pages (or vanish between them) when several share a price.
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Categories that have at least one live product. A category with nothing
     * in it is a dead end for a customer, so it is not offered.
     *
     * @return Collection<int, ProductCategory>
     */
    public function categories(?int $limit = null): Collection
    {
        return ProductCategory::query()
            ->active()
            ->whereHas('products', fn (Builder $query) => $query->published())
            ->ordered()
            ->when($limit !== null, fn (Builder $query) => $query->limit($limit))
            ->get();
    }

    /**
     * One category by its public slug, for the shop's category filter.
     *
     * Inactive categories are not resolved: the filter must offer exactly what
     * the sidebar and the marquee link to, and a hidden category reachable by
     * typing its slug is a leak, not a feature. An unknown slug reads as null
     * so the caller can decide - the shop shows the whole catalogue rather
     * than a 404, because a customer who followed a stale link still wants
     * products.
     */
    public function findCategoryBySlug(?string $slug): ?ProductCategory
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return null;
        }

        return ProductCategory::query()->active()->where('slug', $slug)->first();
    }

    /**
     * One product by its public slug, for /product/{slug}.
     *
     * Goes through the same published() gate as every listing, so the slug of
     * a draft or archived product is a 404 to the public even though it exists
     * in the panel - otherwise an unfinished page would be reachable by anyone
     * who guessed or kept the link.
     */
    public function findBySlug(string $slug): Product
    {
        return $this->base()
            ->with(['detail', 'images', 'packs'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * Products for the "Related products" strip.
     *
     * Same category first, because that is what "related" means to a customer.
     * Topped up from the rest of the catalogue rather than left short, so the
     * four-column grid is never rendered half empty.
     *
     * @return Collection<int, Product>
     */
    public function related(Product $product, int $limit = 4): Collection
    {
        $sameCategory = $this->base()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->orderByDesc('is_featured')
            ->orderByDesc('rating_avg')
            ->limit($limit)
            ->get();

        if ($sameCategory->count() >= $limit) {
            return $sameCategory;
        }

        $fillers = $this->base()
            ->whereKeyNot($product->id)
            ->whereNotIn('id', $sameCategory->modelKeys())
            ->orderByDesc('is_featured')
            ->limit($limit - $sameCategory->count())
            ->get();

        return $sameCategory->concat($fillers);
    }

    public function isValidSort(?string $sort): bool
    {
        return $sort !== null && array_key_exists($sort, self::SORTS);
    }

    /**
     * eager-loads the category because every card prints its name; without it
     * a twelve-card grid would fire twelve extra queries.
     */
    private function base(): Builder
    {
        return Product::query()->published()->with('category:id,name,slug');
    }
}
