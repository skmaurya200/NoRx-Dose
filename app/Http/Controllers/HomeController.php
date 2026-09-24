<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Order;
use App\Models\ProductCategory;
use App\Services\Blog\BlogService;
use App\Services\Catalogue\SearchService;
use App\Services\Catalogue\StorefrontCatalogue;
use App\Services\Commerce\CouponService;
use App\Services\Reviews\ReviewService;
use App\Support\HtmlSanitizer;
use App\Support\PageSeo;
use App\Support\SearchLink;
use App\Support\UsStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly StorefrontCatalogue $catalogue,
        private readonly CouponService $coupons,
        private readonly BlogService $blog,
        private readonly ReviewService $reviews,
    ) {}

    public function index(): View
    {
        // Ten, spread across the categories rather than ten of whatever one
        // category happens to rate highest.
        $featured = $this->catalogue->featuredSpread(10);

        return view('home', [
            'seo' => PageSeo::make()
                ->title(config('seo.site_name').' — '.config('seo.tagline'))
                ->description(config('seo.default_description'))
                ->canonical(route('home')),
            'posts' => $this->blog->latest(3),
            // Every live category, not a first few: the row is a marquee that
            // scrolls, so the length of the list is no longer a layout problem.
            'categories' => $this->catalogue->categories(),
            'featured' => $featured,
            // The two grids sit on one screen, so the second one skips
            // whatever the first already showed.
            'arrivals' => $this->catalogue->latest(10, $featured),
        ]);
    }

    /**
     * GET /shop, optionally narrowed by ?category={slug}.
     *
     * An unknown or hidden slug falls back to the whole catalogue rather than
     * 404ing: somebody who followed a stale link still came here to shop.
     */
    public function shop(Request $request): View
    {
        $sort = (string) $request->query('sort', 'default');

        if (! $this->catalogue->isValidSort($sort)) {
            $sort = 'default';
        }

        $slug = $request->query('category');

        $category = $this->catalogue->findCategoryBySlug(is_string($slug) ? $slug : null);

        return view('shop', [
            'products' => $this->catalogue->paginate($sort, $category),
            'promotion' => $this->coupons->featuredOffer(),
            'sort' => $sort,
            'category' => $category,
            // The sidebar lists every live category, so a customer can move
            // between them without going back to the home page first.
            'categories' => $this->catalogue->categories(),
            'seo' => $this->shopSeo($category),
        ]);
    }

    /**
     * What the shop tells a search engine about itself.
     *
     * A category view is a real, stable collection worth ranking on its own,
     * so it gets its own title, description and canonical rather than being
     * left to compete with the unfiltered page.
     */
    private function shopSeo(?ProductCategory $category): PageSeo
    {
        $seo = PageSeo::make();

        if ($category === null) {
            return $seo->canonical(route('shop'));
        }

        return $seo
            ->title($category->meta_title ?: $category->name.' — Shop')
            ->description($category->meta_description
                ?: Str::limit(HtmlSanitizer::toText($category->description), 155)
                ?: 'Shop '.$category->name.' at '.config('seo.site_name').'.')
            ->canonical(route('shop', ['category' => $category->slug]))
            ->schema(PageSeo::breadcrumbs([
                'Home' => route('home'),
                'Shop' => route('shop'),
                $category->name => route('shop', ['category' => $category->slug]),
            ]));
    }

    /**
     * POST /search - the header box submits here.
     *
     * A GET form would write the term straight into the address bar. This
     * takes it in the request body, seals it, and sends the browser on to the
     * results page, so the term reaches the URL only in its sealed form. A
     * redirect rather than rendering here, so the results page is a plain GET
     * that can be reloaded, bookmarked and paginated.
     */
    public function searchSubmit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return redirect()->to(SearchLink::url((string) ($data['q'] ?? '')));
    }

    /**
     * GET /search?q=…
     *
     * The one place a term is counted. The suggestion endpoint fires on every
     * keystroke, so counting there would record "m", "ma" and "mag" as three
     * searches; landing here is the moment somebody actually searched.
     */
    public function search(Request $request, SearchService $search): View
    {
        // Sealed by App\Support\SearchLink, so the term is not readable in
        // the address bar, the access log or a Referer header. A value that
        // will not open - an edited URL, or a link from before the app key was
        // rotated - reads as no search rather than as an error.
        $term = SearchLink::open($request->query('q'));

        // Capped before it reaches the query. A 4KB "term" is a probe, and
        // truncating it costs a real search nothing.
        $term = Str::limit($term, 120, '');

        $products = $search->paginate($term);

        if ($term !== '') {
            $search->record($term, $products->total());
        }

        return view('search', [
            'term' => $term,
            // The header box lives in the layout, which inherits this view's
            // data - so the term it prefills with is passed under a name of
            // its own rather than colliding with a page's own $term.
            'searchTerm' => $term,
            'products' => $products,
            // Only offered when the search came back empty - a list of other
            // things to try is noise on a page that already has answers.
            'popular' => $products->total() === 0 ? $search->popular() : [],
            'seo' => PageSeo::make()
                ->title($term !== '' ? 'Search: '.$term : 'Search')
                // A results page has no content of its own and would compete
                // with the product pages it links to. Still followed, so the
                // links on it are worth something.
                ->noindex(),
        ]);
    }

    public function allProducts(): View
    {
        return view('all-products', [
            'products' => $this->catalogue->all(),
            'categories' => $this->catalogue->categories(),
        ]);
    }

    /**
     * GET /product/{slug}
     *
     * The slug is looked up through the published gate, so an unfinished
     * product is a 404 to the public even though the panel can see it.
     */
    public function product(string $slug): View
    {
        $product = $this->catalogue->findBySlug($slug);

        return view('product', [
            'product' => $product,
            'related' => $this->catalogue->related($product, 4),
            // Approved only. A review a customer just left is not visible to
            // them either - there is one rule, and it is moderation.
            'reviews' => $this->reviews->forProduct($product, (int) config('shop.reviews.per_product', 20)),
            'reviewSummary' => $this->reviews->summary($product->id),
        ]);
    }

    public function reviews(): View
    {
        $feed = $this->reviews->feed();

        return view('reviews', [
            'summary' => $this->reviews->summary(),
            'categories' => $this->reviews->categoryAverages(),
            'featured' => $this->reviews->featured(),
            'reviews' => $feed,
            // Handed to the page's script as JSON rather than rendered server
            // side: the design filters by category and reveals six at a time
            // in the browser, and paging on the server would fight it.
            'reviewsJson' => $feed->map(fn ($review) => $this->reviews->toPublicArray($review))->all(),
        ]);
    }

    public function shippingPolicy(): View
    {
        return view('shipping-policy');
    }

    public function faq(): View
    {
        return view('faq');
    }

    public function about(): View
    {
        return view('about');
    }

    public function cart(): View
    {
        return view('cart', ['offers' => $this->offers()]);
    }

    public function checkout(): View
    {
        return view('checkout', [
            'offers' => $this->offers(),
            'states' => UsStates::all(),
            'country' => config('shop.country'),
            'countryName' => config('shop.country_name'),
        ]);
    }

    /**
     * GET /order/{token} - the confirmation page.
     *
     * Resolved on the order's public token (see Order::getRouteKeyName), so
     * the page cannot be reached by guessing an id. An unknown token is a 404
     * rather than a redirect: there is nothing useful to show.
     */
    public function orderConfirmation(Order $order): View
    {
        return view('order-confirmation', [
            // items.product for the thumbnails; the line's own snapshot is
            // still what the receipt prints.
            'order' => $order->load(['items.product:id,thumbnail_path', 'payment']),
        ]);
    }

    /**
     * The publicly advertised discount codes, rendered into the page so the
     * cart shows its offers on first paint rather than after a round trip.
     *
     * Measured against a zero subtotal: the page knows its own basket and
     * recomputes each card's eligibility from the same rules as it draws.
     *
     * @return array<int, array<string, mixed>>
     */
    private function offers(): array
    {
        return $this->coupons->offers(0.0);
    }

    /**
     * GET /blogs - the journal listing.
     *
     * Category and search are query parameters handled in SQL rather than by
     * hiding cards in the browser, so the page behaves the same with six posts
     * or six hundred and a filtered view is a shareable URL.
     */
    public function blogs(Request $request): View
    {
        $filters = [
            'category' => trim((string) $request->query('category', '')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $isFiltered = $filters['category'] !== '' || $filters['q'] !== '';

        // The featured post leads an unfiltered first page. Under a filter it
        // would be a post that may not even match, so it is dropped and the
        // grid carries everything.
        $featured = $isFiltered || $request->query('page', 1) > 1
            ? null
            : $this->blog->featured();

        $posts = $this->blog->paginatePublished($filters, $featured?->id);
        $categories = $this->blog->publicCategories();

        return view('blogs', [
            'seo' => $this->journalSeo($filters, $categories, $posts, $featured),
            'featured' => $featured,
            'posts' => $posts,
            'categories' => $categories,
            'filters' => $filters,
            'isFiltered' => $isFiltered,
        ]);
    }

    /**
     * What the journal listing tells a search engine about itself.
     *
     * Two decisions worth naming. A search results page is marked noindex: it
     * has no content of its own and would compete with the posts it links to.
     * A category page is not - it is a real, stable collection worth ranking.
     *
     * @param  array<string, string>  $filters
     */
    private function journalSeo(array $filters, $categories, $posts, ?BlogPost $featured = null): PageSeo
    {
        $category = $filters['category'] !== ''
            ? $categories->firstWhere('slug', $filters['category'])
            : null;

        $seo = PageSeo::make()
            ->canonical(route('blogs', array_filter(['category' => $filters['category'] ?: null])));

        if ($filters['q'] !== '') {
            return $seo
                ->title('Search: '.$filters['q'].' — Journal')
                ->description('Journal posts matching '.$filters['q'].'.')
                ->canonical(route('blogs'))
                ->noindex();
        }

        if ($category !== null) {
            $seo->title($category->name.' — Journal')
                ->description($category->description
                    ?: 'Journal posts about '.$category->name.' from '.config('seo.site_name').'.');
        } else {
            $seo->title('The journal')
                ->description('Notes on ingredients, routines and how the products are actually '
                    .'made. No launch announcements.');
        }

        // Page 2 and beyond carry their own canonical - they are distinct
        // pages with distinct content, not duplicates of page one.
        if ($posts->currentPage() > 1) {
            $seo->canonical($posts->url($posts->currentPage()))
                ->title(($category?->name ?? 'The journal').' — page '.$posts->currentPage());
        }

        $seo->paginate(
            $posts->currentPage() > 1 ? $posts->previousPageUrl() : null,
            $posts->hasMorePages() ? $posts->nextPageUrl() : null,
        );

        $trail = ['Home' => route('home'), 'Journal' => route('blogs')];

        if ($category !== null) {
            $trail[$category->name] = route('blogs', ['category' => $category->slug]);
        }

        return $seo
            ->schema(PageSeo::breadcrumbs($trail))
            ->schema([
                '@type' => 'Blog',
                '@id' => route('blogs').'#blog',
                'name' => config('seo.site_name').' journal',
                'url' => route('blogs'),
                'publisher' => ['@id' => url('/').'#organization'],
                // The featured post is drawn above the grid and excluded from
                // it, so it has to be put back here or the page would describe
                // itself as listing one post fewer than it shows.
                'blogPost' => collect($featured ? [$featured] : [])
                    ->concat($posts->getCollection())
                    ->map(fn (BlogPost $post) => [
                        '@type' => 'BlogPosting',
                        'headline' => $post->title,
                        'url' => $post->url(),
                        'datePublished' => $post->published_at?->toAtomString(),
                    ])->values()->all(),
            ]);
    }

    /**
     * GET /blogs/{slug}
     */
    public function blogDetails(string $slug): View
    {
        $post = $this->blog->findPublished($slug);

        // A draft or a scheduled post is a 404, not a redirect: as far as a
        // visitor is concerned it does not exist yet.
        abort_if($post === null, 404);

        $this->blog->countView($post);

        return view('blog-details', [
            'seo' => $this->postSeo($post),
            'post' => $post,
            'related' => $this->blog->related($post, 3),
        ]);
    }

    /**
     * The article tags and the BlogPosting schema for one post.
     */
    private function postSeo(BlogPost $post): PageSeo
    {
        $trail = ['Home' => route('home'), 'Journal' => route('blogs')];

        if ($post->category) {
            $trail[$post->category->name] = route('blogs', ['category' => $post->category->slug]);
        }

        $trail[$post->title] = $post->url();

        $article = [
            '@type' => 'BlogPosting',
            '@id' => $post->url().'#post',
            'headline' => $post->title,
            'description' => $post->seoDescription(),
            'url' => $post->url(),
            // The canonical page this article belongs to, which is what stops
            // a syndicated copy being read as the original.
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $post->url()],
            'datePublished' => $post->published_at?->toAtomString(),
            // A post edited after publication says so; a search engine treats
            // a stale-looking article differently from a maintained one.
            'dateModified' => $post->updated_at?->toAtomString(),
            'author' => ['@type' => 'Organization', 'name' => $post->author_name],
            'publisher' => ['@id' => url('/').'#organization'],
            'wordCount' => str_word_count(HtmlSanitizer::toText($post->body)),
            'isPartOf' => ['@id' => route('blogs').'#blog'],
        ];

        if ($post->category) {
            $article['articleSection'] = $post->category->name;
        }

        if ($post->coverUrl()) {
            $article['image'] = $post->coverUrl();
        }

        return PageSeo::make()
            ->title($post->seoTitle())
            ->description($post->seoDescription())
            ->canonical($post->url())
            ->image($post->coverUrl(), $post->coverAlt())
            ->article(
                $post->published_at?->toAtomString(),
                $post->updated_at?->toAtomString(),
                $post->author_name,
                $post->category?->name,
            )
            // Live and linkable, but kept out of the index when the operator
            // asked for that.
            ->noindex(! $post->is_indexable)
            ->schema(PageSeo::breadcrumbs($trail))
            ->schema($article);
    }
}
