<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Browsing the shop one category at a time.
 *
 * The category links on the home page, in the footer and in the shop's own
 * sidebar all lead to the same place - /shop?category={slug} - so this covers
 * both ends: that the links are drawn, and that the page they lead to narrows
 * the catalogue rather than only looking as though it has.
 */
class ShopCategoryTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategory $sleep;

    private ProductCategory $energy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sleep = ProductCategory::factory()->create([
            'name' => 'Sleep & Recovery',
            'slug' => 'sleep-recovery',
            'description' => '<p>Wind down and repair overnight.</p>',
        ]);

        $this->energy = ProductCategory::factory()->create([
            'name' => 'Daily Energy',
            'slug' => 'daily-energy',
        ]);
    }

    /* -------------------------------------------------------------- filter */

    public function test_the_shop_shows_only_the_products_of_the_category_in_the_url(): void
    {
        Product::factory()->create(['category_id' => $this->sleep->id, 'name' => 'Night Blend']);
        Product::factory()->create(['category_id' => $this->energy->id, 'name' => 'Morning Blend']);

        $response = $this->get('/shop?category=sleep-recovery');

        $response->assertSee('Night Blend');
        $response->assertDontSee('Morning Blend');
    }

    public function test_a_draft_product_stays_hidden_inside_a_category(): void
    {
        Product::factory()->draft()->create([
            'category_id' => $this->sleep->id,
            'name' => 'Unfinished Blend',
        ]);

        $this->get('/shop?category=sleep-recovery')->assertDontSee('Unfinished Blend');
    }

    public function test_an_unknown_category_slug_falls_back_to_the_whole_catalogue(): void
    {
        Product::factory()->create(['category_id' => $this->energy->id, 'name' => 'Morning Blend']);

        // A stale link is not a dead end: somebody who followed it still came
        // here to shop.
        $this->get('/shop?category=no-such-category')->assertSee('Morning Blend');
    }

    public function test_an_inactive_category_cannot_be_browsed_by_typing_its_slug(): void
    {
        $hidden = ProductCategory::factory()->inactive()->create(['slug' => 'hidden-shelf']);

        Product::factory()->create(['category_id' => $hidden->id, 'name' => 'Hidden Blend']);
        Product::factory()->create(['category_id' => $this->energy->id, 'name' => 'Morning Blend']);

        $response = $this->get('/shop?category=hidden-shelf');

        // The filter is ignored rather than honoured, so the page is the plain
        // catalogue and not a private shelf reachable by guessing a slug.
        $response->assertSee('Morning Blend');
    }

    public function test_the_sort_order_still_applies_inside_a_category(): void
    {
        Product::factory()->create([
            'category_id' => $this->sleep->id, 'name' => 'Cheap Blend', 'price' => 10,
        ]);
        Product::factory()->create([
            'category_id' => $this->sleep->id, 'name' => 'Costly Blend', 'price' => 90,
        ]);
        Product::factory()->create(['category_id' => $this->energy->id, 'name' => 'Morning Blend']);

        $response = $this->get('/shop?category=sleep-recovery&sort=low');

        $response->assertDontSee('Morning Blend');
        $response->assertSeeInOrder(['Cheap Blend', 'Costly Blend']);
    }

    /* --------------------------------------------------------- description */

    public function test_the_category_description_is_printed_under_the_products(): void
    {
        Product::factory()->create(['category_id' => $this->sleep->id, 'name' => 'Night Blend']);

        $response = $this->get('/shop?category=sleep-recovery');

        // Stored markup is printed unescaped, because HtmlSanitizer already
        // filtered it on the way in.
        $response->assertSeeInOrder(
            ['Night Blend', 'Wind down and repair overnight.'],
            false,
        );
    }

    public function test_the_unfiltered_shop_prints_no_category_description(): void
    {
        Product::factory()->create(['category_id' => $this->sleep->id]);

        $this->get('/shop')->assertDontSee('Wind down and repair overnight.');
    }

    public function test_the_categorys_own_sections_are_drawn_under_its_description(): void
    {
        $this->sleep->update([
            'accordions' => [
                ['label' => 'How to choose', 'body' => '<h3>Strength</h3><p>Start low.</p>'],
            ],
        ]);

        Product::factory()->create(['category_id' => $this->sleep->id, 'name' => 'Night Blend']);

        $this->get('/shop?category=sleep-recovery')
            ->assertOk()
            ->assertSeeInOrder([
                'Wind down and repair overnight.',
                'How to choose',
                '<h3>Strength</h3>',
            ], false);
    }

    public function test_a_section_without_an_icon_still_gets_a_tile(): void
    {
        $this->sleep->update([
            'accordions' => [['label' => 'How to choose', 'icon' => '', 'body' => '<p>Start low.</p>']],
        ]);

        Product::factory()->create(['category_id' => $this->sleep->id]);

        // The tile is part of the row, so a section nobody gave an icon to has
        // to look like the ones that have one rather than leaving a gap.
        $this->get('/shop?category=sleep-recovery')
            ->assertOk()
            ->assertSee('class="catacc__ic"', false)
            ->assertSee('◆', false);
    }

    public function test_a_category_with_no_sections_draws_no_accordion(): void
    {
        Product::factory()->create(['category_id' => $this->sleep->id]);

        $this->get('/shop?category=sleep-recovery')
            ->assertOk()
            ->assertDontSee('catacc__head', false);
    }

    /* --------------------------------------------------------------- links */

    public function test_the_shop_sidebar_links_to_every_live_category(): void
    {
        Product::factory()->create(['category_id' => $this->sleep->id]);
        Product::factory()->create(['category_id' => $this->energy->id]);

        $response = $this->get('/shop');

        $response->assertSee(route('shop', ['category' => 'sleep-recovery']), false);
        $response->assertSee(route('shop', ['category' => 'daily-energy']), false);
    }

    public function test_a_category_with_no_live_products_is_not_offered(): void
    {
        $empty = ProductCategory::factory()->create(['slug' => 'empty-shelf']);

        Product::factory()->draft()->create(['category_id' => $empty->id]);
        Product::factory()->create(['category_id' => $this->sleep->id]);

        // A category that leads to an empty page is a dead end, so no link to
        // it is drawn anywhere on the storefront.
        $this->get('/shop')->assertDontSee(route('shop', ['category' => 'empty-shelf']), false);
    }

    public function test_the_home_page_category_row_links_to_the_filtered_shop(): void
    {
        Product::factory()->create(['category_id' => $this->sleep->id]);

        $this->get('/')->assertSee(route('shop', ['category' => 'sleep-recovery']), false);
    }

    public function test_the_footer_lists_seven_categories_and_links_each_to_the_shop(): void
    {
        // Eight live categories, so the cut is exercised rather than assumed.
        // sort_order is pinned because the footer takes the first seven in that
        // order - left to the factory's random value, which of them survives
        // the cut would be luck, and so would this test.
        $categories = ProductCategory::factory()
            ->count(8)
            ->sequence(fn ($sequence) => ['sort_order' => $sequence->index])
            ->create();

        foreach ($categories as $category) {
            Product::factory()->create(['category_id' => $category->id]);
        }

        $column = $this->footerCategoryColumn($this->get('/shop')->getContent());

        // Minus one: the column ends with its own "All products" link, which
        // is not a category.
        $this->assertSame(7, substr_count($column, '<li>') - 1);
        // The first by sort order is in; the last is the one that was cut.
        $this->assertStringContainsString(
            route('shop', ['category' => $categories->first()->slug]),
            $column,
        );
        $this->assertStringNotContainsString(
            route('shop', ['category' => $categories->last()->slug]),
            $column,
        );
    }

    /**
     * The markup of the footer's category column on its own, so a count is not
     * thrown off by the same links appearing in the shop's sidebar.
     */
    private function footerCategoryColumn(string $html): string
    {
        $column = explode('<h5>Categories</h5>', $html)[1] ?? '';

        return explode('</ul>', $column)[0] ?? '';
    }
}
