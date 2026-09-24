<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three public listing pages: home, shop and all-products.
 *
 * These guard two things that are easy to break silently - that only live
 * products reach a customer, and that the markup the stylesheets depend on is
 * still the markup being rendered.
 */
class CatalogueListingTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ProductCategory::factory()->create(['name' => 'Sleep & Recovery']);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge(
            ['category_id' => $this->category->id],
            $attributes,
        ));
    }

    /* ----------------------------------------------------------- visibility */

    public function test_only_published_products_appear_on_the_storefront(): void
    {
        $this->product(['name' => 'Live Product']);
        $this->product(['name' => 'Draft Product', 'status' => 'draft']);
        $this->product(['name' => 'Archived Product', 'status' => 'archived']);

        foreach (['/', '/shop', '/all-products'] as $url) {
            $response = $this->get($url)->assertOk();

            $response->assertSee('Live Product');
            $response->assertDontSee('Draft Product');
            $response->assertDontSee('Archived Product');
        }
    }

    public function test_a_product_scheduled_for_the_future_is_not_shown_yet(): void
    {
        $this->product([
            'name' => 'Tomorrow Product',
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $this->get('/shop')->assertOk()->assertDontSee('Tomorrow Product');
    }

    public function test_a_deleted_product_disappears_from_the_storefront(): void
    {
        $product = $this->product(['name' => 'Removed Product']);

        $this->get('/shop')->assertOk()->assertSee('Removed Product');

        $product->delete();

        $this->get('/shop')->assertOk()->assertDontSee('Removed Product');
    }

    /* --------------------------------------------------------- all products */

    public function test_each_categorys_description_is_carried_for_its_chip(): void
    {
        $this->category->update(['description' => '<p>Everything for winding down.</p>']);

        $quiet = ProductCategory::factory()->create([
            'name' => 'Daily Energy',
            'description' => '<p>For the afternoon.</p>',
        ]);

        $this->product();
        Product::factory()->create(['category_id' => $quiet->id]);

        $response = $this->get('/all-products')->assertOk();

        // One block per category, hidden until its own chip is chosen - the
        // chips filter in the browser, so a round trip per paragraph would be
        // the slower answer.
        $response->assertSee('data-cat-note="Sleep &amp; Recovery"', false);
        $response->assertSee('data-cat-note="Daily Energy"', false);
        $response->assertSee('Everything for winding down.');
        $response->assertSee('For the afternoon.');
    }

    public function test_a_categorys_sections_sit_above_its_description(): void
    {
        $this->category->update([
            'description' => '<p>Everything for winding down.</p>',
            'accordions' => [
                ['label' => 'How to choose', 'body' => '<p>Start with the lowest dose.</p>'],
            ],
        ]);

        $this->product();

        // Sections first - they are what somebody choosing between these
        // products wants - and the description underneath them.
        $this->get('/all-products')
            ->assertOk()
            ->assertSeeInOrder([
                'catacc__head',
                'How to choose',
                'Start with the lowest dose.',
                'catnote__title',
                'Everything for winding down.',
            ], false);
    }

    public function test_a_category_with_no_sections_draws_no_accordion(): void
    {
        $this->category->update([
            'description' => '<p>Everything for winding down.</p>',
            'accordions' => null,
        ]);

        $this->product();

        $this->get('/all-products')
            ->assertOk()
            ->assertSee('Everything for winding down.')
            ->assertDontSee('catacc__head', false);
    }

    public function test_the_category_row_can_be_stepped_through(): void
    {
        $this->product();

        // The arrows are the point: the row drifts on its own, and somebody who
        // wants to go back has no way to without them.
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-label="Previous categories"', false)
            ->assertSee('aria-label="Next categories"', false);
    }

    public function test_a_category_with_no_description_gets_no_block(): void
    {
        $bare = ProductCategory::factory()->create([
            'name' => 'Skin', 'description' => null, 'accordions' => null,
        ]);

        Product::factory()->create(['category_id' => $bare->id]);

        $this->get('/all-products')
            ->assertOk()
            ->assertDontSee('data-cat-note="Skin"', false);
    }

    /* ----------------------------------------------------------------- home */

    public function test_the_home_page_fills_both_grids(): void
    {
        Product::factory()->count(14)->create(['category_id' => $this->category->id]);

        $response = $this->get('/')->assertOk();

        $html = $response->getContent();
        $favourites = substr($html, strpos($html, 'id="favs"'), strpos($html, 'id="fresh"') - strpos($html, 'id="favs"'));
        $arrivals = substr($html, strpos($html, 'id="fresh"'));

        // Ten each, five to a row - the carousel that hid half of them is gone.
        $this->assertSame(10, substr_count($favourites, 'class="card__title"'));
        $this->assertSame(10, substr_count($arrivals, 'class="card__title"'));
    }

    public function test_the_favourites_grid_takes_one_from_every_category_first(): void
    {
        // Twelve strong products in one category would fill the grid on their
        // own; the point of the spread is that they do not.
        Product::factory()->count(12)->create([
            'category_id' => $this->category->id,
            'rating_avg' => 5.0,
        ]);

        $quiet = ProductCategory::factory()->create(['name' => 'Daily Energy']);
        $rare = ProductCategory::factory()->create(['name' => 'Skin']);

        Product::factory()->create([
            'category_id' => $quiet->id, 'name' => 'Morning Blend', 'rating_avg' => 1.0,
        ]);
        Product::factory()->create([
            'category_id' => $rare->id, 'name' => 'Night Cream', 'rating_avg' => 1.0,
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $favourites = substr($html, strpos($html, 'id="favs"'), strpos($html, 'id="fresh"') - strpos($html, 'id="favs"'));

        // Both are the worst-rated products in the shop, and both are in - a
        // customer should see the range, not twelve of the same thing.
        $this->assertStringContainsString('Morning Blend', $favourites);
        $this->assertStringContainsString('Night Cream', $favourites);
        $this->assertSame(10, substr_count($favourites, 'class="card__title"'));
    }

    public function test_both_grids_offer_a_way_to_the_rest_of_the_catalogue(): void
    {
        Product::factory()->count(3)->create(['category_id' => $this->category->id]);

        $response = $this->get('/')->assertOk();

        // One in each heading, and one under each grid for the phone, where the
        // heading is long gone by the time the last card is read.
        $this->assertSame(4, substr_count($response->getContent(), 'View all products'));
        $response->assertSee(route('all-products'), false);
    }

    public function test_neither_grid_is_a_carousel_any_more(): void
    {
        Product::factory()->create(['category_id' => $this->category->id]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('data-rail=', false)
            ->assertDontSee('rail__btn', false);
    }

    public function test_the_arrivals_grid_is_not_left_empty_on_a_small_catalogue(): void
    {
        // Every product is a favourite here, so a hard exclusion would render
        // an empty "New arrivals" section - which reads as a broken page.
        Product::factory()->count(3)->featured()->create(['category_id' => $this->category->id]);

        $html = $this->get('/')->assertOk()->getContent();
        $arrivals = substr($html, strpos($html, 'id="fresh"'));

        $this->assertSame(3, substr_count($arrivals, 'class="card__title"'));
    }

    public function test_the_home_page_lists_real_categories(): void
    {
        $this->product();
        ProductCategory::factory()->create(['name' => 'Empty Category']);

        $response = $this->get('/')->assertOk();

        $response->assertSee('Sleep &amp; Recovery', false);
        // A category with nothing live in it is a dead end for a customer.
        $response->assertDontSee('Empty Category');
    }

    /* ----------------------------------------------------------------- shop */

    public function test_the_shop_paginates_and_reports_a_real_count(): void
    {
        Product::factory()->count(15)->create(['category_id' => $this->category->id]);

        $response = $this->get('/shop')->assertOk();

        $response->assertSee('>1–12<', false);
        $response->assertSee('>15<', false);
        // The pager markup the stylesheet targets.
        $response->assertSee('class="pager"', false);
        $response->assertSee('aria-label="Next page"', false);

        $this->get('/shop?page=2')->assertOk()->assertSee('class="is-here"', false);
    }

    public function test_the_shop_sorts_by_price(): void
    {
        $this->product(['name' => 'Cheapest', 'price' => 5.00, 'compare_at_price' => null]);
        $this->product(['name' => 'Dearest', 'price' => 500.00, 'compare_at_price' => null]);

        $low = $this->get('/shop?sort=low')->assertOk()->getContent();
        $high = $this->get('/shop?sort=high')->assertOk()->getContent();

        $this->assertLessThan(strpos($low, 'Dearest'), strpos($low, 'Cheapest'));
        $this->assertLessThan(strpos($high, 'Cheapest'), strpos($high, 'Dearest'));
    }

    public function test_an_unknown_sort_value_falls_back_instead_of_failing(): void
    {
        $this->product();

        // Feeding this into orderBy() would be SQL injection; the allow-list
        // means it is simply ignored.
        $this->get('/shop?sort='.urlencode('price); drop table tbl_products;--'))
            ->assertOk();

        $this->assertDatabaseCount('tbl_products', 1);
    }

    public function test_the_shop_keeps_the_sort_when_paging(): void
    {
        Product::factory()->count(15)->create(['category_id' => $this->category->id]);

        $this->get('/shop?sort=low')
            ->assertOk()
            // Losing the sort on page two would reshuffle the catalogue
            // underneath the customer.
            ->assertSee('sort=low', false);
    }

    /* --------------------------------------------------------- all products */

    public function test_all_products_renders_every_live_product_with_its_category(): void
    {
        $other = ProductCategory::factory()->create(['name' => 'Daily Essentials']);

        $this->product(['name' => 'Sleep Item']);
        Product::factory()->create(['category_id' => $other->id, 'name' => 'Daily Item']);

        $response = $this->get('/all-products')->assertOk();

        $response->assertSee('Sleep Item');
        $response->assertSee('Daily Item');
        // data-cat is what the chip filter matches against in the browser.
        $response->assertSee('data-cat="Daily Essentials"', false);
        $response->assertSee('2 products');
    }

    public function test_all_products_offers_a_chip_per_stocked_category(): void
    {
        $this->product();

        $response = $this->get('/all-products')->assertOk();

        $response->assertSee('data-cat="all"', false);
        $response->assertSee('data-cat="Sleep &amp; Recovery"', false);
    }

    /* ---------------------------------------------------------------- cards */

    public function test_a_card_shows_a_struck_through_price_only_when_there_is_one(): void
    {
        $this->product(['name' => 'On Offer', 'price' => 10, 'compare_at_price' => 20]);

        $this->get('/shop')->assertOk()
            ->assertSee('price__was', false)
            ->assertSee('SAVE 50%');

        Product::query()->delete();
        $this->product(['name' => 'Full Price', 'price' => 10, 'compare_at_price' => null]);

        $this->get('/shop')->assertOk()->assertDontSee('price__was', false);
    }

    /**
     * A card always shows a picture. Without a photo of its own it gets the
     * default bottle, marked so the CSS letterboxes it rather than cropping a
     * drawing to fill the box.
     */
    public function test_a_card_falls_back_to_the_default_bottle_without_a_photo(): void
    {
        $this->product();

        $this->get('/shop')
            ->assertOk()
            ->assertSee('images/defaults/product.svg', false)
            ->assertSee('jar__default', false);
    }

    public function test_a_card_shows_the_product_photo_when_there_is_one(): void
    {
        $product = $this->product();
        $product->forceFill(['thumbnail_path' => 'uploads/products/example.webp'])->save();

        $this->get('/shop')->assertOk()->assertSee('uploads/products/example.webp', false);
    }

    public function test_the_shop_card_makes_no_claim_about_recent_purchases(): void
    {
        $this->product();

        // There is no orders table yet, so any "N people bought this" figure
        // would be invented - and invented social proof on a live storefront is
        // a false claim, not a placeholder.
        $this->get('/shop')->assertOk()->assertDontSee('bought this in the last');
    }

    public function test_an_empty_catalogue_renders_without_errors(): void
    {
        foreach (['/', '/shop', '/all-products'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/shop')->assertSee('Nothing in the shop yet');
        $this->get('/all-products')->assertSee('0 products');
    }
}
