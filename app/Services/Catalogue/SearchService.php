<?php

namespace App\Services\Catalogue;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SearchQuery;
use App\Support\DefaultImage;
use App\Support\SearchLink;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the storefront search box does.
 *
 * Two callers with different needs: the dropdown under the header wants a
 * handful of matches as fast as possible, and the results page wants the
 * whole set, paginated. Both read through Product::published(), so a draft can
 * never surface in either.
 *
 * Terms are counted in tbl_search_queries as they are run. That is what feeds
 * the "popular searches" the dropdown offers before anything is typed, and
 * what tells the shop which searches come back empty.
 */
class SearchService
{
    /**
     * Below this, a term matches most of the catalogue and the suggestions are
     * noise. One character is not a search.
     */
    public const MIN_LENGTH = 2;

    /**
     * The dropdown under the header.
     *
     * Products first because that is what a shop's search box is for;
     * categories and journal posts follow, capped tightly, so the panel stays
     * scannable rather than becoming a second results page.
     *
     * @return array<string, mixed>
     */
    public function suggest(string $term, int $limit = 6): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            // Nothing typed yet: offer what other people search for. It is the
            // one thing worth showing in an empty box.
            return [
                'term' => $term,
                'products' => [],
                'categories' => [],
                'posts' => [],
                // Sealed here rather than in the browser: the page has no key,
                // so every link to a search has to be built server side.
                'popular' => $this->popularLinks(),
                'total' => 0,
                'results_url' => null,
            ];
        }

        $products = $this->productQuery($term)->limit($limit)->get();

        return [
            'term' => $term,
            'products' => $products->map(fn (Product $product) => $this->describeProduct($product))->all(),
            'categories' => $this->categories($term)->map(fn (ProductCategory $category) => [
                'name' => $category->name,
                'url' => route('all-products').'#'.$category->slug,
            ])->all(),
            'posts' => $this->posts($term)->map(fn (BlogPost $post) => [
                'title' => $post->title,
                'url' => route('blog-details', $post->slug),
            ])->all(),
            'popular' => [],
            // The real count, not the page: "12 results" under a list of six
            // is what tells someone it is worth pressing enter.
            'total' => $this->productQuery($term)->count(),
            'results_url' => SearchLink::url($term),
        ];
    }

    /**
     * The results page.
     *
     * A term too short to search returns nothing rather than everything. It
     * matters more than it looks: SqlLike::contains('') is "%%", which matches
     * every row, so without this an empty or one-character search would hand
     * back the whole catalogue as though it had found it.
     */
    public function paginate(string $term, int $perPage = 12): LengthAwarePaginator
    {
        $term = trim($term);

        $query = mb_strlen($term) < self::MIN_LENGTH
            ? Product::query()->whereRaw('1 = 0')
            : $this->productQuery($term);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Counts a term, and what it found.
     *
     * Called from the results page rather than from the dropdown: the dropdown
     * fires on every keystroke, and counting "m", "ma", "mag" as three
     * searches would make the numbers meaningless. Pressing enter is the
     * moment somebody actually searched for something.
     */
    public function record(string $term, int $resultCount): void
    {
        $normalised = SearchQuery::normalise($term);

        if (mb_strlen($normalised) < self::MIN_LENGTH) {
            return;
        }

        // upsert plus a raw increment rather than read-modify-write: two
        // people searching the same word in the same instant would otherwise
        // both read the same count and store it once.
        SearchQuery::query()->upsert(
            [[
                'term' => $normalised,
                'search_count' => 1,
                'result_count' => $resultCount,
                'last_searched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['term'],
            [
                'search_count' => DB::raw('search_count + 1'),
                'result_count' => $resultCount,
                'last_searched_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * The popular terms with their sealed URLs, for the suggestion panel.
     *
     * @return array<int, array{term: string, url: string}>
     */
    public function popularLinks(int $limit = 6): array
    {
        return array_map(
            fn (string $term) => ['term' => $term, 'url' => SearchLink::url($term)],
            $this->popular($limit),
        );
    }

    /**
     * The terms offered in an empty box.
     *
     * Only terms that found something: suggesting a search the shop cannot
     * answer would send people to an empty page on purpose.
     *
     * @return array<int, string>
     */
    public function popular(int $limit = 6): array
    {
        return SearchQuery::query()
            ->where('result_count', '>', 0)
            ->popular()
            ->limit($limit)
            ->pluck('term')
            ->all();
    }

    /* -------------------------------------------------------------- private */

    /**
     * Live products matching a term, best match first.
     *
     * The ordering is the point. A LIKE match tells you whether a row matches,
     * not how well: without this, searching "magnesium" would put a product
     * that merely mentions it in its description above the one called
     * Magnesium Complex. So a name that starts with the term outranks a name
     * that contains it, which outranks everything else.
     */
    public function productQuery(string $term): Builder
    {
        $contains = SqlLike::contains($term);
        $starts = SqlLike::escape($term).'%';

        return Product::query()
            ->published()
            ->with('category:id,name,slug')
            ->where(function (Builder $query) use ($contains) {
                $query->where('name', 'like', $contains)
                    ->orWhere('brand', 'like', $contains)
                    ->orWhere('sku', 'like', $contains)
                    ->orWhere('short_description', 'like', $contains)
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $contains));
            })
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$starts, $contains])
            ->orderByDesc('is_featured')
            ->orderByDesc('rating_count')
            ->orderBy('name');
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    private function categories(string $term, int $limit = 3): Collection
    {
        return ProductCategory::query()
            ->where('is_active', true)
            ->where('name', 'like', SqlLike::contains($term))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug']);
    }

    /**
     * @return Collection<int, BlogPost>
     */
    private function posts(string $term, int $limit = 3): Collection
    {
        return BlogPost::query()
            ->published()
            ->where('title', 'like', SqlLike::contains($term))
            ->newest()
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'published_at']);
    }

    /**
     * One product as the dropdown needs it. Deliberately small - a suggestion
     * is a line, not a card.
     *
     * @return array<string, mixed>
     */
    private function describeProduct(Product $product): array
    {
        return [
            'name' => $product->name,
            'category' => $product->category?->name,
            'price' => $product->priceFormatted(),
            'image' => $product->thumbnailUrl() ?? DefaultImage::product(),
            'url' => route('product', $product->slug),
        ];
    }
}
