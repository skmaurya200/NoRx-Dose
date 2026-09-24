<?php

namespace Tests\Feature\Storefront;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SearchQuery;
use App\Support\SearchLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header search box: the suggestion endpoint behind it and the results
 * page it submits to.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    private const SUGGEST = '/api/storefront/search';

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ProductCategory::factory()->create([
            'name' => 'Minerals',
            'is_active' => true,
        ]);
    }

    /**
     * The results page for a term. The term is sealed into the URL, so a test
     * builds the link the same way every other caller does rather than
     * spelling out a query string that would not work.
     */
    private function results(string $term)
    {
        return $this->get(SearchLink::url($term));
    }

    private function product(string $name, array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'name' => $name,
            'status' => 'active',
        ], $overrides));
    }

    public function test_the_header_places_a_mobile_search_trigger_before_the_cart(): void
    {
        $response = $this->get('/shop');

        $response
            ->assertOk()
            ->assertSee('id="mobileSearchToggle"', false)
            ->assertSee('aria-controls="searchBox"', false)
            ->assertSeeInOrder(['id="mobileSearchToggle"', 'aria-label="Open cart"'], false);
    }

    /* ---------------------------------------------------------- suggestions */

    public function test_a_product_is_suggested_by_name(): void
    {
        $this->product('Calm Magnesium Complex');
        $this->product('Vitamin D3 Drops');

        $response = $this->getJson(self::SUGGEST.'?q=magnesium')->assertOk();

        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.products.0.name', 'Calm Magnesium Complex');
        $response->assertJsonPath('data.products.0.category', 'Minerals');
        $this->assertStringContainsString('/product/', $response->json('data.products.0.url'));
    }

    public function test_a_product_is_found_by_brand_category_and_description(): void
    {
        $this->product('Nightly Wind-Down', [
            'brand' => 'Aurum Labs',
            'short_description' => 'A blend built around ashwagandha.',
        ]);

        $this->getJson(self::SUGGEST.'?q=ashwagandha')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson(self::SUGGEST.'?q=aurum labs')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson(self::SUGGEST.'?q=minerals')->assertOk()->assertJsonPath('data.total', 1);
    }

    /**
     * A LIKE match says whether a row matches, not how well. Without the
     * ordering, a product that merely mentions the word in its description
     * would outrank the one named after it.
     */
    public function test_a_name_that_starts_with_the_term_comes_first(): void
    {
        $this->product('Evening Blend', ['short_description' => 'Contains magnesium.']);
        $this->product('Magnesium Complex');

        $this->getJson(self::SUGGEST.'?q=magnesium')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Magnesium Complex');
    }

    public function test_a_draft_product_is_never_suggested(): void
    {
        $this->product('Secret Magnesium', ['status' => 'draft']);

        $this->getJson(self::SUGGEST.'?q=magnesium')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_categories_and_journal_posts_are_suggested_too(): void
    {
        BlogPost::factory()->create([
            'category_id' => BlogCategory::factory(),
            'title' => 'Why we use magnesium glycinate',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->getJson(self::SUGGEST.'?q=magnesium')
            ->assertOk()
            ->assertJsonPath('data.posts.0.title', 'Why we use magnesium glycinate');

        $this->getJson(self::SUGGEST.'?q=minerals')
            ->assertOk()
            ->assertJsonPath('data.categories.0.name', 'Minerals');
    }

    /**
     * One character matches most of the catalogue, so the box holds off.
     */
    public function test_a_single_character_returns_nothing_but_the_popular_terms(): void
    {
        $this->product('Magnesium Complex');
        SearchQuery::factory()->create(['term' => 'vitamin d', 'search_count' => 9]);

        $this->getJson(self::SUGGEST.'?q=m')
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.popular.0.term', 'vitamin d');
    }

    public function test_an_empty_box_offers_only_terms_that_found_something(): void
    {
        SearchQuery::factory()->create(['term' => 'collagen', 'search_count' => 20, 'result_count' => 4]);
        SearchQuery::factory()->unanswered()->create(['term' => 'protein powder', 'search_count' => 99]);

        $response = $this->getJson(self::SUGGEST)->assertOk();

        $this->assertSame(['collagen'], array_column($response->json('data.popular'), 'term'));
    }

    /**
     * "%" and "_" are wildcards in a LIKE, so an unescaped term would match
     * every row and turn a cheap lookup into a full scan.
     */
    public function test_like_wildcards_in_a_term_are_taken_literally(): void
    {
        $this->product('Magnesium Complex');

        $this->getJson(self::SUGGEST.'?q=%')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson(self::SUGGEST.'?q=__')->assertOk()->assertJsonPath('data.total', 0);
    }

    /* --------------------------------------------------------- results page */

    public function test_the_results_page_lists_the_matches(): void
    {
        $this->product('Calm Magnesium Complex');
        $this->product('Vitamin D3 Drops');

        $this->results('magnesium')
            ->assertOk()
            ->assertSee('Calm Magnesium Complex')
            ->assertDontSee('Vitamin D3 Drops')
            // The count and the word are separate nodes in the markup, so the
            // two are asserted separately rather than as one string.
            ->assertSee('<b>1</b>', false)
            ->assertSee('result for');
    }

    public function test_the_results_page_is_kept_out_of_the_index(): void
    {
        $this->results('magnesium')
            ->assertOk()
            ->assertSee('noindex, follow', false);
    }

    /**
     * SqlLike::contains('') is "%%", which matches every row. Without a guard
     * an empty search would hand back the whole catalogue as though it had
     * found it, which is the worst of both - wrong, and expensive.
     */
    public function test_an_empty_or_one_character_search_returns_nothing(): void
    {
        $this->product('Calm Magnesium Complex');

        $this->get(route('search'))
            ->assertOk()
            ->assertDontSee('Calm Magnesium Complex');

        $this->results('m')
            ->assertOk()
            ->assertDontSee('Calm Magnesium Complex');
    }

    public function test_an_empty_search_still_renders(): void
    {
        $this->get('/search')->assertOk()->assertSee('Nothing searched for yet');
    }

    /* -------------------------------------------------------------- counting */

    public function test_a_search_is_counted_once_per_visit_to_the_results_page(): void
    {
        $this->product('Calm Magnesium Complex');

        $this->results('Magnesium')->assertOk();
        $this->results('  magnesium  ')->assertOk();

        // Both spellings normalise to one row.
        $this->assertSame(1, SearchQuery::query()->count());

        $row = SearchQuery::query()->firstOrFail();

        $this->assertSame('magnesium', $row->term);
        $this->assertSame(2, $row->search_count);
        $this->assertSame(1, $row->result_count);
        $this->assertNotNull($row->last_searched_at);
    }

    public function test_a_search_that_finds_nothing_is_recorded_with_zero_results(): void
    {
        $this->results('protein powder')->assertOk();

        $this->assertDatabaseHas('tbl_search_queries', [
            'term' => 'protein powder',
            'result_count' => 0,
        ]);
    }

    /**
     * The suggestion endpoint fires on every keystroke, so counting there
     * would record "m", "ma" and "mag" as three searches.
     */
    public function test_the_suggestion_endpoint_records_nothing(): void
    {
        $this->product('Calm Magnesium Complex');

        $this->getJson(self::SUGGEST.'?q=magnesium')->assertOk();

        $this->assertSame(0, SearchQuery::query()->count());
    }

    public function test_a_term_too_short_to_search_is_not_recorded(): void
    {
        $this->results('m')->assertOk();

        $this->assertSame(0, SearchQuery::query()->count());
    }

    /**
     * Nothing about the person searching is stored - only the words.
     */
    public function test_only_the_term_is_stored(): void
    {
        $this->results('magnesium')->assertOk();

        $columns = array_keys(SearchQuery::query()->firstOrFail()->getAttributes());

        foreach (['ip_address', 'session_id', 'user_agent', 'user_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }

    /* --------------------------------------------------------------- sealing */

    /**
     * The whole point: the term is not readable in the address bar, so it does
     * not end up in the browser history, the access log, or a Referer header
     * on its way to somebody else's server.
     */
    public function test_the_term_is_not_readable_in_the_url(): void
    {
        $url = SearchLink::url('magnesium');

        $this->assertStringNotContainsString('magnesium', $url);
        $this->assertStringContainsString('q=', $url);
    }

    public function test_the_box_posts_and_is_redirected_to_a_sealed_url(): void
    {
        $response = $this->post('/search', ['q' => 'magnesium'])->assertRedirect();

        $target = $response->headers->get('Location');

        $this->assertStringNotContainsString('magnesium', $target);

        // And the redirect target is a working results page.
        $this->product('Calm Magnesium Complex');
        $this->get($target)->assertOk()->assertSee('Calm Magnesium Complex');
    }

    public function test_posting_an_empty_term_lands_on_the_bare_results_page(): void
    {
        $this->post('/search', ['q' => ''])
            ->assertRedirect(route('search'));
    }

    /**
     * A sealed value is only meaningful to this application. Anything else -
     * an edited URL, a plain term typed in by hand, a link from before the app
     * key was rotated - reads as no search rather than as an error.
     */
    public function test_a_term_that_will_not_open_reads_as_no_search(): void
    {
        $this->product('Calm Magnesium Complex');

        foreach (['magnesium', 'not-a-sealed-value', '../../etc/passwd'] as $raw) {
            $this->get('/search?q='.urlencode($raw))
                ->assertOk()
                ->assertSee('Nothing searched for yet')
                ->assertDontSee('Calm Magnesium Complex');
        }

        // And none of them were counted as a search.
        $this->assertSame(0, SearchQuery::query()->count());
    }

    public function test_the_box_is_prefilled_with_the_term_on_the_results_page(): void
    {
        $this->results('magnesium')
            ->assertOk()
            ->assertSee('value="magnesium"', false);
    }

    /**
     * Every link the storefront offers to a search has to be sealed, or one of
     * them would quietly put the term back in the open.
     */
    public function test_the_popular_links_on_an_empty_results_page_are_sealed(): void
    {
        SearchQuery::factory()->create([
            'term' => 'collagen', 'search_count' => 12, 'result_count' => 3,
        ]);

        $this->results('nothing at all here')
            ->assertOk()
            ->assertSee('collagen')
            ->assertDontSee('q=collagen', false);
    }

    public function test_the_suggestion_endpoint_hands_back_a_sealed_results_url(): void
    {
        $this->product('Calm Magnesium Complex');

        $url = $this->getJson(self::SUGGEST.'?q=magnesium')
            ->assertOk()
            ->json('data.results_url');

        $this->assertStringNotContainsString('magnesium', $url);
        $this->assertSame('magnesium', SearchLink::open(
            (string) parse_url($url, PHP_URL_QUERY) ? explode('q=', $url)[1] : '',
        ));
    }
}
