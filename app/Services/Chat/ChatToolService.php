<?php

namespace App\Services\Chat;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SearchQuery;
use App\Support\SqlLike;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only door between the assistant and this shop's data.
 *
 * The model never reaches a table. It names one of these functions and gives
 * arguments; this class validates them, runs a query it wrote itself, and
 * hands back a small shaped array. That ordering is the whole point - a tool
 * the model cannot describe is a tool it cannot reach, and an argument it
 * invents is an argument that fails validation here rather than becoming SQL.
 *
 * Two rules hold for every method. Nothing returns more than a handful of
 * records, because a prompt is not a place to put a catalogue. And nothing
 * returns a field an answer has no business quoting - cost price, stock
 * counts, customer data and internal identifiers all stay on this side.
 */
class ChatToolService
{
    /**
     * The most any single call will return, whatever it was asked for.
     *
     * A limit the model cannot argue with: it may ask for five hundred
     * products and still get this many, because a prompt is paid for by the
     * shop rather than by whoever typed the question.
     */
    public const MAX_RECORDS = 5;

    public function __construct(
        private readonly ChatProductSearchService $products,
        private readonly ChatRetrievalService $retrieval,
        private readonly ChatStoreFactsService $facts,
    ) {}

    /**
     * Runs one named function on the model's behalf.
     *
     * Unknown names throw rather than returning an error the model could read
     * and work around. A tool this shop has no data for is never offered in
     * the first place, so being asked for one means something is out of step.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments): array
    {
        if (! array_key_exists($name, $this->available())) {
            throw new InvalidArgumentException('Unknown assistant function: '.$name);
        }

        return match ($name) {
            'searchProducts' => $this->searchProducts(
                $this->text($arguments, 'query'),
                $arguments,
                $this->page($arguments),
            ),
            'getProductDetails' => $this->getProductDetails($this->text($arguments, 'product')),
            'findSimilarProducts' => $this->findSimilarProducts($this->text($arguments, 'product')),
            'searchCategories' => $this->searchCategories($this->text($arguments, 'query')),
            'getWebsiteInformation' => $this->getWebsiteInformation($this->text($arguments, 'topic')),
            'getStoreInformation' => ['found' => true] + $this->facts->all(),
            'getTrendingProducts' => $this->getTrendingProducts(),
            'getPopularProducts' => $this->getPopularProducts(),
            'getPopularSearches' => $this->getPopularSearches(),
            default => throw new InvalidArgumentException('Unknown assistant function: '.$name),
        };
    }

    /**
     * The functions worth offering, given what this shop currently knows.
     *
     * An analytics function with no analytics behind it is worse than a
     * missing one: the model calls it, gets nothing back, and either
     * apologises at length or fills the gap itself. A shop that has never
     * taken an order is not asked what is trending.
     *
     * @return array<string, string> name => what it is for
     */
    public function available(): array
    {
        $tools = [
            'searchProducts' => "Search this shop's catalogue for what the customer is looking for. "
                .'Use it for any request to find, browse, list or compare products, including vague '
                .'ones such as "something for sleep". Returns up to five products with live prices.',
            'getProductDetails' => 'Everything this shop has stored about one named product - its '
                .'description, pack sizes, live price, rating and whether it can be ordered.',
            'findSimilarProducts' => 'Other products a customer looking at one named product might want '
                .'instead. Use it when the customer asks for something similar, an alternative, or '
                .'another option like the one being discussed.',
            'searchCategories' => "The shop's product categories matching a word, for when the "
                .'customer asks what kinds of thing are sold.',
            'getWebsiteInformation' => "Search this shop's own written pages - FAQ, policies, about, "
                .'journal posts and category descriptions - for a topic. Use it for questions about '
                .'policies, returns, ingredients or anything the store has written about.',
            'getStoreInformation' => 'Facts about how this store works, read from its live configuration: '
                .'its name, what you (the assistant) can and cannot do, ordering, payment methods, '
                .'delivery methods and costs, what happens after an order, discount codes, reviews, '
                .'page links and the public contact details. Use it for questions about the store, '
                .'about you, or how to reach the team.',
        ];

        if ($this->hasSales()) {
            $tools['getTrendingProducts'] = 'What customers have been ordering most over the last month.';
            $tools['getPopularProducts'] = "What has sold the most across the shop's whole history.";
        }

        if (SearchQuery::query()->exists()) {
            $tools['getPopularSearches'] = "What visitors most often type into the shop's search box.";
        }

        return $tools;
    }

    /**
     * The argument shapes, as the provider's own function declarations.
     *
     * Only the arguments this class actually reads are described. An argument
     * the model cannot name is one it cannot smuggle a filter through.
     *
     * @return list<array<string, mixed>>
     */
    public function declarations(): array
    {
        $schemas = [
            'searchProducts' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'What the customer is looking for, in their own words.',
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Restrict to a category name, when the customer named one.',
                    ],
                    'brand' => [
                        'type' => 'string',
                        'description' => 'Restrict to a brand, when the customer named one.',
                    ],
                    'min_price' => ['type' => 'number', 'description' => 'Only products at or above this price.'],
                    'max_price' => ['type' => 'number', 'description' => 'Only products at or below this price.'],
                    'exclude_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => 'Product ids already shown to the customer, to leave out.',
                    ],
                    'in_stock' => [
                        'type' => 'boolean',
                        'description' => 'True for only what can be ordered now, false for only what cannot. '
                            .'Leave it out unless the customer asked about availability.',
                    ],
                    'page' => ['type' => 'integer', 'description' => 'Result page, starting at 1.'],
                ],
                'required' => ['query'],
            ],
            'getProductDetails' => [
                'type' => 'object',
                'properties' => [
                    'product' => [
                        'type' => 'string',
                        'description' => 'The product name as the customer gave it.',
                    ],
                ],
                'required' => ['product'],
            ],
            'findSimilarProducts' => [
                'type' => 'object',
                'properties' => [
                    'product' => [
                        'type' => 'string',
                        'description' => 'The product to find alternatives to, by name.',
                    ],
                ],
                'required' => ['product'],
            ],
            'searchCategories' => [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string']],
                'required' => ['query'],
            ],
            'getWebsiteInformation' => [
                'type' => 'object',
                'properties' => [
                    'topic' => [
                        'type' => 'string',
                        'description' => "The question, or its subject, in the customer's words.",
                    ],
                ],
                'required' => ['topic'],
            ],
            'getStoreInformation' => ['type' => 'object', 'properties' => (object) []],
        ];

        $declarations = [];

        foreach ($this->available() as $name => $purpose) {
            $declarations[] = [
                'name' => $name,
                'description' => $purpose,
                'parameters' => $schemas[$name] ?? ['type' => 'object', 'properties' => (object) []],
            ];
        }

        return $declarations;
    }

    /* ----------------------------------------------------------- catalogue */

    /**
     * Products matching what was asked for, with prices read live.
     *
     * The shortlist retrieval produced is preferred when there is one, because
     * "something for tired afternoons" has no keyword to search on; the
     * keyword query is what answers a named product. Either way the rows come
     * from the catalogue at the moment of asking, so a price in an answer is
     * the price on the page.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function searchProducts(string $query, array $filters = [], int $page = 1): array
    {
        $results = $this->products->paginate(
            $this->searchContext(['query' => $query] + $filters),
            max(1, $page),
        );

        $products = array_map(
            fn (Product $product): array => $this->productSummary($product),
            $results->items(),
        );

        /* Exactness is reported, not guessed at. A search for "Zolpidem 10mg"
           in a shop that has never stocked it used to come back "found: 9" -
           nine products, none of them Zolpidem - and the only thing standing
           between that and a customer being sold the wrong medicine was the
           model noticing. Now the result says plainly which of the two things
           happened, and says it in words rather than in a flag the model has
           to interpret. */
        $exact = $this->exactProduct($query);

        // Only worth saying when there is something to be mistaken for it. A
        // search that found nothing needs no warning about what it found.
        $asked = $this->asksForOneProduct($query) && $results->total() > 0;

        $result = [
            'searched_for' => $query,
            'exact_match' => $exact === null ? null : $this->productSummary($exact),
            'found' => $results->total(),
            'showing' => $results->count(),
            'page' => $results->currentPage(),
            'has_more' => $results->hasMorePages(),
            'products' => $products,
        ];

        if ($exact !== null) {
            // The one they named goes first, whatever the ranking thought.
            $result['products'] = $this->exactFirst($products, $exact);

            return $result;
        }

        if ($asked) {
            $result['note'] = 'This shop has no product called "'.$query.'". The products listed are '
                .'DIFFERENT products - do not describe any of them as "'.$query.'". Say the requested '
                .'product was not found, then offer these separately as alternatives the customer may '
                .'want instead.';
        }

        return $result;
    }

    /**
     * The named product at the head of its own results.
     *
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function exactFirst(array $products, Product $exact): array
    {
        $rest = array_values(array_filter(
            $products,
            static fn (array $product): bool => (int) $product['id'] !== $exact->id,
        ));

        return array_slice(array_merge([$this->productSummary($exact)], $rest), 0, self::MAX_RECORDS);
    }

    /**
     * Other products a customer looking at this one might want.
     *
     * Its own category first, because that is this shop's own judgement of
     * what belongs beside what, and then whatever the index finds close to it.
     * Never the product itself: "show me similar products" is a request to be
     * shown something else.
     *
     * @return array<string, mixed>
     */
    public function findSimilarProducts(string $product): array
    {
        $match = $this->findProduct($product);

        if ($match === null) {
            return ['found' => false, 'searched_for' => $product, 'similar_to' => null, 'products' => []];
        }

        $similar = Product::query()
            ->published()
            ->with('category:id,name,slug')
            ->whereKeyNot($match->id)
            ->when($match->category_id !== null, fn (Builder $builder) => $builder
                ->where('category_id', $match->category_id))
            ->orderBy('price')
            ->limit(self::MAX_RECORDS)
            ->get();

        if ($similar->isEmpty()) {
            // Nothing else in its category, so fall back to what the index
            // thinks it is near - a shop with one product per category still
            // has to be able to answer this.
            $ids = collect($this->retrieval->search($match->name))
                ->where('type', 'product')
                ->pluck('source_id')
                ->filter()
                ->reject(fn ($id): bool => (int) $id === $match->id)
                ->take(self::MAX_RECORDS)
                ->all();

            $similar = $ids === [] ? collect() : Product::query()
                ->published()
                ->with('category:id,name,slug')
                ->whereIn('id', $ids)
                ->get();
        }

        return [
            'found' => $similar->isNotEmpty(),
            'similar_to' => $match->name,
            'category' => $match->category?->name,
            'products' => $similar->map(fn (Product $p): array => $this->productSummary($p))->values()->all(),
        ];
    }

    /**
     * The catalogue query behind a product search, as the search service wants it.
     *
     * Built here rather than inside searchProducts() because the answer that
     * comes back needs the same context to page through: "load more" has to
     * mean more of what was asked for, not a fresh guess at it.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function searchContext(array $arguments): array
    {
        $query = $this->text($arguments, 'query');
        $context = ['query' => $query];

        foreach (['category', 'brand'] as $key) {
            $value = $this->text($arguments, $key);

            if ($value !== '') {
                $context[$key] = $value;
            }
        }

        foreach (['min_price', 'max_price'] as $key) {
            if (isset($arguments[$key]) && is_numeric($arguments[$key])) {
                $context[$key] = max(0, (float) $arguments[$key]);
            }
        }

        if (is_bool($arguments['in_stock'] ?? null)) {
            $context['in_stock'] = $arguments['in_stock'];
        }

        // "Show me more" and "not the ones you already showed me" are the same
        // query with a hole in it. The hole is passed down rather than the
        // results being filtered afterwards, or a page of five could come back
        // as a page of one.
        $exclude = array_values(array_filter(array_map('intval', $arguments['exclude_ids'] ?? [])));

        if ($exclude !== []) {
            $context['exclude_ids'] = $exclude;
        }

        // Meaning first, but only when the customer stated no filters of their
        // own: a semantic shortlist must never quietly widen "under 500" into
        // a list of everything that felt similar.
        $stated = $context;
        unset($stated['exclude_ids']);

        if ($stated === ['query' => $query] && $query !== '') {
            $ids = collect($this->retrieval->search($query))
                ->where('type', 'product')
                ->pluck('source_id')
                ->filter()
                ->values()
                ->all();

            if ($ids !== []) {
                $context['ids'] = $ids;
            }
        }

        return $context;
    }

    /**
     * Everything this shop has written about one product.
     *
     * @return array<string, mixed>
     */
    public function getProductDetails(string $product): array
    {
        $match = $this->findProduct($product);

        if ($match === null) {
            return ['found' => false, 'searched_for' => $product, 'product' => null];
        }

        $match->loadMissing(['category:id,name,slug', 'detail', 'packs']);

        $packs = $match->packs
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($pack): array => [
                'size' => $pack->label,
                'price' => (float) $pack->price,
                'best_value' => (bool) $pack->is_best_value,
            ])
            ->all();

        return [
            'found' => true,
            'product' => $this->productSummary($match) + [
                'short_description' => $match->short_description,
                'description' => $this->plain((string) ($match->detail?->description ?? $match->description)),
                'pack_sizes' => $packs,
                'rating' => $match->rating_count > 0 ? (float) $match->rating_avg : null,
                'rating_count' => (int) $match->rating_count,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function searchCategories(string $query): array
    {
        $categories = ProductCategory::query()
            ->when($query !== '', fn (Builder $builder) => $builder
                ->where('name', 'like', SqlLike::contains($query)))
            ->withCount(['products' => fn (Builder $builder) => $builder->published()])
            ->orderByDesc('products_count')
            ->limit(self::MAX_RECORDS)
            ->get();

        return [
            'found' => $categories->count(),
            'categories' => $categories->map(fn (ProductCategory $category): array => [
                'name' => $category->name,
                'products' => (int) $category->products_count,
                'url' => route('shop', ['category' => $category->slug]),
            ])->all(),
        ];
    }

    /* --------------------------------------------------------- the website */

    /**
     * What this shop has written on its own pages about a topic.
     *
     * How the store works (payment, delivery, ordering) is getStoreInformation,
     * read from configuration; this is the prose an operator wrote - FAQ,
     * policies, about, journal - found by meaning, then by words.
     *
     * @return array<string, mixed>
     */
    public function getWebsiteInformation(string $topic): array
    {
        $matches = $this->retrieval->search($topic);

        if ($matches === []) {
            $matches = $this->retrieval->searchLoose($topic);
        }

        $records = [];

        foreach ($matches as $match) {
            if (count($records) >= self::MAX_RECORDS) {
                break;
            }

            if (in_array($match['type'], ['faq', 'page', 'post', 'category'], true)) {
                $records[] = [
                    'title' => $match['title'],
                    'body' => $this->cap((string) $match['body']),
                ];
            }
        }

        return ['found' => $records !== [], 'information' => $records];
    }

    /* ---------------------------------------------------------- what sells */

    /**
     * What has been ordered most over the last month.
     *
     * @return array<string, mixed>
     */
    public function getTrendingProducts(): array
    {
        return $this->soldMost(now()->subDays(30));
    }

    /**
     * What has been ordered most since the shop opened.
     *
     * @return array<string, mixed>
     */
    public function getPopularProducts(): array
    {
        return $this->soldMost(null);
    }

    /**
     * What visitors type into the shop's own search box.
     *
     * @return array<string, mixed>
     */
    public function getPopularSearches(): array
    {
        $rows = SearchQuery::query()
            ->popular()
            ->limit(self::MAX_RECORDS)
            ->get(['term', 'search_count', 'result_count']);

        return [
            'found' => $rows->isNotEmpty(),
            'searches' => $rows->map(fn (SearchQuery $row): array => [
                'term' => $row->term,
                'times_searched' => (int) $row->search_count,
                'products_found' => (int) $row->result_count,
            ])->all(),
        ];
    }

    /**
     * Order volume by product, optionally only since a date.
     *
     * Counted from order lines and then matched back to the catalogue, so a
     * product that has since been unpublished drops out rather than being
     * recommended. A cancelled order is not interest.
     *
     * @return array<string, mixed>
     */
    private function soldMost(?DateTimeInterface $since): array
    {
        $sold = OrderItem::query()
            ->join('tbl_orders', 'tbl_orders.id', '=', 'tbl_order_items.order_id')
            ->whereNull('tbl_orders.deleted_at')
            ->where('tbl_orders.status', '!=', 'cancelled')
            ->when($since !== null, fn ($query) => $query->where('tbl_orders.placed_at', '>=', $since))
            ->whereNotNull('tbl_order_items.product_id')
            ->selectRaw('tbl_order_items.product_id, SUM(tbl_order_items.quantity) as sold')
            ->groupBy('tbl_order_items.product_id')
            ->orderByDesc('sold')
            ->limit(self::MAX_RECORDS)
            ->pluck('sold', 'product_id');

        if ($sold->isEmpty()) {
            return ['found' => false, 'products' => []];
        }

        $products = Product::query()
            ->published()
            ->with('category:id,name,slug')
            ->whereIn('id', $sold->keys())
            ->get()
            ->sortByDesc(fn (Product $product): int => (int) $sold[$product->id])
            ->values();

        return [
            'found' => $products->isNotEmpty(),
            'products' => $products->map(fn (Product $product): array => $this->productSummary($product) + [
                'times_ordered' => (int) $sold[$product->id],
            ])->all(),
        ];
    }

    /* -------------------------------------------------------------- shared */

    /**
     * What the functions returned, flattened into text an answer is checked against.
     *
     * Every figure is written in more than one spelling on purpose. A price
     * comes back as 90.0 and as "$90.00", and an answer that says "$90" is
     * quoting the same price - the grounding check compares whole figures, so
     * without this a correct answer would be thrown away for writing the
     * number the way a person would.
     *
     * @param  list<array<string, mixed>>  $calls
     */
    public function evidence(array $calls): string
    {
        $lines = [];

        foreach ($calls as $call) {
            // The arguments count as well as the results. "We do not have
            // Zolpidem 10mg" is a true answer that names something the results
            // could not contain, because the whole point is that it was not
            // found - and the name came from the customer in the first place.
            $this->flatten($call['arguments'] ?? [], $lines);
            $this->flatten($call['result'] ?? [], $lines);
        }

        return implode(' ', array_unique($lines));
    }

    /**
     * @param  mixed  $value
     * @param  list<string>  $lines
     */
    private function flatten($value, array &$lines): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $lines[] = str_replace('_', ' ', $key);
                }

                $this->flatten($item, $lines);
            }

            return;
        }

        if (is_bool($value) || $value === null) {
            return;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return;
        }

        $lines[] = $text;

        if (is_numeric($value)) {
            $number = (float) $value;

            $lines[] = number_format($number, 2, '.', '');
            $lines[] = number_format($number, 2, '.', ',');
            $lines[] = (string) (int) $number;
        }
    }

    /**
     * Whether any of the functions actually came back with something.
     *
     * A search that found nothing and a product that does not exist are both
     * honest results, but they are not material an answer can be checked
     * against - so "I could not find that" has nothing to overlap with, and
     * holding it to a records check would reject the one reply that is true.
     *
     * @param  list<array<string, mixed>>  $calls
     */
    public function foundAnything(array $calls): bool
    {
        foreach ($calls as $call) {
            $found = $call['result']['found'] ?? false;

            if ($found === true || (is_int($found) && $found > 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The product ids behind a tool result, for the cards under an answer.
     *
     * @param  array<string, mixed>  $result
     * @return list<int>
     */
    public function productIds(array $result): array
    {
        return array_values(array_filter(array_map(
            static fn (array $product): int => (int) ($product['id'] ?? 0),
            $this->products($result),
        )));
    }

    /**
     * The products in a tool result, whatever shape it came back in.
     *
     * @param  array<string, mixed>  $result
     * @return list<array<string, mixed>>
     */
    public function products(array $result): array
    {
        $products = $result['products'] ?? null;

        if (! is_array($products)) {
            $products = [];
        }

        // A single-product result, and the exact match a search reports
        // alongside its list, are products too.
        foreach (['product', 'exact_match'] as $key) {
            if (is_array($result[$key] ?? null)) {
                array_unshift($products, $result[$key]);
            }
        }

        return array_values(array_filter($products, static fn ($product): bool => is_array($product)
            && ($product['id'] ?? null) !== null));
    }

    /**
     * One product, as much of it as an answer may quote and no more.
     *
     * Price and availability are read from the row here rather than from the
     * search index, which is why a stale index can pick the wrong product but
     * can never quote the wrong price. Cost price and stock counts are not on
     * this list and must not be added to it.
     *
     * @return array<string, mixed>
     */
    private function productSummary(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'category' => $product->category?->name,
            'brand' => $product->brand,
            'price' => (float) $product->price,
            'price_formatted' => $product->priceFormatted(),
            'currency' => $product->currency,
            'available' => ! $product->isOutOfStock(),
            'url' => route('product', $product->slug),
        ];
    }

    /**
     * A strength or dose, which is how a customer names one specific product.
     *
     * "Zolpidem 10mg" is a request for that product; "something for sleep" is
     * not. The difference decides whether a result set is an answer or a list
     * of other things, so it is worth being explicit about.
     */
    private const NAMES_A_DOSE = '/\d+\s*(?:mg|mcg|ug|g|ml|iu)\b/i';

    /**
     * Whether the query is asking for one particular product by name.
     *
     * The dose is the whole test, and deliberately the only one. Asking
     * whether the words open the name of something on the shelves sounds
     * stronger and is not: this shop sells Magnesium 1 through 7, so
     * "magnesium" opened a product name and a plain browse was reported as a
     * product that could not be found. A strength is what people type when
     * they mean one exact thing.
     */
    private function asksForOneProduct(string $query): bool
    {
        return $query !== '' && preg_match(self::NAMES_A_DOSE, $query) === 1;
    }

    /**
     * The product whose name is what was typed, if there is one.
     *
     * Exact before similar, always. A customer who types a product name has
     * told us which product they mean, and a search that answers with five
     * near neighbours has changed the question.
     */
    public function exactProduct(string $query): ?Product
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $normalised = $this->normaliseName($query);

        // Name for name first, then ignoring the spacing people vary - "10 mg"
        // against "10mg" is the same product and a different string.
        $candidates = Product::query()
            ->published()
            ->with('category:id,name,slug')
            ->where(function (Builder $builder) use ($query): void {
                $builder->whereRaw('LOWER(name) = ?', [mb_strtolower($query)])
                    ->orWhere('name', 'like', SqlLike::contains($query));
            })
            ->orderByRaw('LENGTH(name)')
            ->limit(20)
            ->get();

        foreach ($candidates as $product) {
            if ($this->normaliseName($product->name) === $normalised) {
                return $product;
            }
        }

        return null;
    }

    /**
     * A product name reduced to what a customer varies without meaning to.
     */
    private function normaliseName(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii($name)));
    }

    /**
     * The product a customer meant, by name.
     *
     * An exact name first, then the shortest containing match, and only then
     * the search index - so "Zolpidem 10mg" finds the 10mg rather than
     * whichever Zolpidem happened to score best.
     */
    private function findProduct(string $name): ?Product
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $exact = $this->exactProduct($name);

        if ($exact !== null) {
            return $exact;
        }

        $like = Product::query()
            ->published()
            ->where('name', 'like', SqlLike::contains($name))
            ->orderByRaw('LENGTH(name)')
            ->first();

        if ($like !== null) {
            return $like;
        }

        $match = collect($this->retrieval->search($name))->firstWhere('type', 'product');

        return $match === null ? null : Product::query()->published()->find($match['source_id']);
    }

    private function hasSales(): bool
    {
        return OrderItem::query()->whereNotNull('product_id')->exists();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function text(array $arguments, string $key): string
    {
        $value = $arguments[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function page(array $arguments): int
    {
        $page = $arguments['page'] ?? 1;

        return is_numeric($page) ? max(1, (int) $page) : 1;
    }

    private function cap(string $body): string
    {
        return mb_substr($body, 0, (int) config('chat.retrieval.max_body_characters', 2000));
    }

    private function plain(string $html): string
    {
        return $this->cap(trim((string) preg_replace('/\s+/u', ' ', strip_tags($html))));
    }
}
