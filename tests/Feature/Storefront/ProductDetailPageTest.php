<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\DefaultImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public product page at /product/{slug}.
 */
class ProductDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ProductCategory::factory()->create(['name' => 'Sleep & Recovery']);
    }

    private function product(array $attributes = [], array $detail = []): Product
    {
        $product = Product::factory()->create(array_merge(
            ['category_id' => $this->category->id],
            $attributes,
        ));

        if ($detail !== []) {
            $product->detail()->create($detail);
        }

        return $product->fresh();
    }

    /* ------------------------------------------------------- buy controls */

    /**
     * Both "Buy now" buttons need an id: product.js binds them by one, and
     * without it they were markup with nothing behind them.
     */
    public function test_both_buy_now_buttons_are_addressable(): void
    {
        $product = $this->product();

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('id="buyNow"', false)
            ->assertSee('id="sBuy"', false);
    }

    /**
     * type="button" on all four: a bare <button> defaults to type="submit",
     * which would post a form if one is ever wrapped around the buy box.
     */
    public function test_the_buy_controls_do_not_submit(): void
    {
        $product = $this->product();

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();

        foreach (['addCart', 'sCart', 'buyNow', 'sBuy'] as $id) {
            $this->assertMatchesRegularExpression(
                '/<button type="button"[^>]*id="'.$id.'"/',
                $html,
                $id.' should be type="button"',
            );
        }
    }

    /**
     * Buy now sends the shopper to the checkout, so the page has to carry its
     * address - the layout publishes it on window.AURUM_SHOP.
     */
    public function test_the_page_knows_where_the_checkout_is(): void
    {
        $product = $this->product();

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('AURUM_SHOP', false)
            ->assertSee('checkout', false);
    }

    /**
     * The cart and the checkout draw the product's own picture, so the page
     * has to hand one over when the line is added.
     */
    public function test_the_page_carries_a_picture_for_the_cart_line(): void
    {
        $product = $this->product();
        $product->forceFill(['thumbnail_path' => 'uploads/products/hero.webp'])->save();

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('data-product-image="'.asset('uploads/products/hero.webp').'"', false);
    }

    public function test_a_product_without_a_photo_hands_over_the_default(): void
    {
        $product = $this->product();

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('data-product-image="'.DefaultImage::product().'"', false);
    }

    /* ------------------------------------------------------------ routing */

    public function test_a_product_is_reachable_by_its_own_slug(): void
    {
        $product = $this->product(['name' => 'Calm Magnesium Complex']);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('Calm Magnesium Complex')
            ->assertSee('Sleep &amp; Recovery', false);
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->get('/product/no-such-product')->assertNotFound();
    }

    public function test_a_draft_product_is_not_reachable_by_slug(): void
    {
        $product = $this->product(['status' => 'draft']);

        // Visible in the panel, invisible to the public - otherwise an
        // unfinished page is reachable by anyone who keeps the link.
        $this->get('/product/'.$product->slug)->assertNotFound();
    }

    public function test_an_archived_product_is_not_reachable_by_slug(): void
    {
        $product = $this->product(['status' => 'archived']);

        $this->get('/product/'.$product->slug)->assertNotFound();
    }

    public function test_a_deleted_product_is_not_reachable_by_slug(): void
    {
        $product = $this->product();
        $slug = $product->slug;
        $product->delete();

        $this->get('/product/'.$slug)->assertNotFound();
    }

    public function test_a_product_scheduled_for_the_future_is_not_reachable_yet(): void
    {
        $product = $this->product(['published_at' => now()->addWeek()]);

        $this->get('/product/'.$product->slug)->assertNotFound();
    }

    public function test_listing_cards_link_to_the_product_slug(): void
    {
        $product = $this->product();

        foreach (['/', '/shop', '/all-products'] as $url) {
            $this->get($url)->assertOk()->assertSee('/product/'.$product->slug, false);
        }
    }

    /* ------------------------------------------------------------ contents */

    public function test_the_page_shows_the_real_price_and_saving(): void
    {
        $product = $this->product(['price' => 42.00, 'compare_at_price' => 52.00]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('$42.00')
            ->assertSee('$52.00')
            ->assertSee('Save 19%');
    }

    public function test_no_struck_through_price_when_there_is_no_previous_price(): void
    {
        $product = $this->product(['price' => 42.00, 'compare_at_price' => null]);

        $response = $this->get('/product/'.$product->slug)->assertOk();

        $response->assertSee('$42.00');
        $response->assertDontSee('Save ');
    }

    public function test_the_size_selector_lists_the_products_packs(): void
    {
        $product = $this->product(['price' => 38.00]);

        $product->packs()->createMany([
            ['label' => '30 count', 'price' => 38.00, 'compare_at_price' => 46.00, 'stock_quantity' => 3, 'sort_order' => 1],
            ['label' => '60 count', 'price' => 68.00, 'compare_at_price' => 92.00, 'stock_quantity' => 24, 'is_best_value' => true, 'sort_order' => 2],
            ['label' => '90 count', 'price' => 96.00, 'stock_quantity' => 18, 'sort_order' => 3],
        ]);

        $response = $this->get('/product/'.$product->slug)->assertOk();

        // product.js paints the selector from data-packs.
        $response->assertSee('Select size');
        $response->assertSee('30 count', false);
        $response->assertSee('60 count', false);
        $response->assertSee('90 count', false);
    }

    public function test_the_full_description_gets_its_own_section(): void
    {
        $product = $this->product([
            'short_description' => 'A calming evening blend.',
            'description' => '<h2>About this blend</h2><p>Made in small batches.</p>',
        ]);

        $product->detail()->create(['benefits' => 'Helps you wind down.']);

        // Both, and in their own places. The short one used to stand in for the
        // long one, which meant a product with both never showed the long one.
        // The long one sits last in the panel - after the columns a customer
        // skims - so the order is part of the contract.
        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSeeInOrder([
                'A calming evening blend.',
                'Helps you wind down.',
                '<h2>About this blend</h2>',
                'Made in small batches.',
            ], false);
    }

    public function test_a_product_with_no_full_description_draws_no_empty_block(): void
    {
        $product = $this->product([
            'short_description' => 'A calming evening blend.',
            'description' => null,
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertDontSee('class="rich"', false);
    }

    public function test_sections_an_operator_added_appear_after_the_fixed_ones(): void
    {
        $product = $this->product();

        $product->detail()->create([
            'ingredients' => 'Magnesium glycinate',
            'warnings' => 'Not for children',
            'extra_sections' => [
                ['label' => 'What is in the box', 'body' => 'One bottle and a scoop.'],
            ],
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSeeInOrder([
                'Full ingredient list',
                'Safety and warnings',
                'What is in the box',
                'One bottle and a scoop.',
            ]);
    }

    public function test_an_operators_section_is_printed_as_the_markup_it_was_written_in(): void
    {
        $product = $this->product();

        $product->detail()->create([
            'extra_sections' => [
                ['label' => 'What is in the box', 'body' => '<h3>Contents</h3><ul><li>One bottle</li></ul>'],
            ],
        ]);

        // Written in the panel's editor and filtered by HtmlSanitizer on the
        // way in, so the page prints it rather than escaping it.
        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('<h3>Contents</h3>', false)
            ->assertSee('<li>One bottle</li>', false);
    }

    public function test_the_fixed_detail_fields_are_still_escaped(): void
    {
        $product = $this->product();

        // Ingredients, storage and warnings are plain textareas. Nothing typed
        // into one may reach the page as markup.
        $product->detail()->create(['ingredients' => '<script>alert(1)</script>']);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_size_selector_never_shows_the_shipping_weight(): void
    {
        $product = $this->product([
            'unit' => '300 g',
            'weight_grams' => 420.00,
            'price' => 25.00,
        ]);

        $product->packs()->create([
            'label' => '30 count', 'price' => 25.00, 'stock_quantity' => 5, 'sort_order' => 1,
        ]);

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();

        $packs = substr($html, strpos($html, 'data-packs='), 600);

        $this->assertStringContainsString('30 count', $packs);

        // Weight is logistics. It is what the courier is told, never something
        // a customer picks between, and it stays out of the chooser.
        $this->assertStringNotContainsString('420', $packs);
    }

    public function test_the_best_value_size_is_flagged_to_the_chooser(): void
    {
        $product = $this->product();

        $product->packs()->create(['label' => '30', 'price' => 20.00, 'stock_quantity' => 5, 'sort_order' => 1]);
        $product->packs()->create([
            'label' => '90', 'price' => 48.00, 'stock_quantity' => 5, 'sort_order' => 2, 'is_best_value' => true,
        ]);

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();

        preg_match('/data-packs="([^"]*)"/', $html, $matches);

        $packs = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        // The badge is drawn in the browser from this flag, so this is where a
        // missing badge would actually start.
        $this->assertFalse($packs[0]['best']);
        $this->assertTrue($packs[1]['best']);
    }

    public function test_the_size_selector_labels_each_card_with_the_unit(): void
    {
        $product = $this->product(['weight_grams' => 420.00]);

        $product->packs()->create([
            'label' => '30', 'price' => 25.00, 'stock_quantity' => 5, 'sort_order' => 1,
        ]);

        // One word for the whole shop, so a "30" card reads as 30 of
        // something rather than as a bare number.
        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('data-unit-label="'.config('shop.pack_unit_label').'"', false);
    }

    public function test_a_product_without_packs_hides_the_size_chooser(): void
    {
        $product = $this->product(['unit' => '300 g', 'price' => 25.00]);

        $response = $this->get('/product/'.$product->slug)->assertOk();

        // One meaningless option is worse than none; the buy box still works
        // from the product's own price. Asserted on the visible heading, not
        // the string - the hidden container keeps its aria-label either way.
        $response->assertDontSee('class="packHead"', false);
        $response->assertSee('data-packs="[]"', false);
        $response->assertSee('$25.00');
    }

    public function test_the_best_value_flag_reaches_the_page(): void
    {
        $product = $this->product();

        $product->packs()->createMany([
            ['label' => '30 count', 'price' => 38.00, 'sort_order' => 1],
            ['label' => '60 count', 'price' => 68.00, 'is_best_value' => true, 'sort_order' => 2],
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('&quot;best&quot;:true', false);
    }

    /* --------------------------------------------------------------- stock */

    public function test_a_backorder_product_tells_the_page_it_is_still_buyable(): void
    {
        $product = $this->product([
            'track_inventory' => true,
            'stock_quantity' => 0,
            'allow_backorder' => true,
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('data-allow-backorder="1"', false);
    }

    public function test_a_product_without_backorders_carries_no_flag(): void
    {
        $product = $this->product([
            'track_inventory' => true,
            'stock_quantity' => 0,
            'allow_backorder' => false,
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('data-allow-backorder=""', false);
    }

    public function test_the_detail_copy_is_rendered_where_it_exists(): void
    {
        $product = $this->product(['short_description' => 'Three forms of magnesium.'], [
            'ingredients' => 'Magnesium glycinate 200 mg',
            'benefits' => 'Supports normal muscle function',
            'how_to_use' => 'Two capsules before bed.',
            'storage' => 'Keep in a cool, dry place.',
            'warnings' => 'Do not exceed the stated dose.',
            'country_of_origin' => 'United Kingdom',
            'is_vegetarian' => true,
            'specifications' => [['label' => 'Serving size', 'value' => '2 capsules']],
        ]);

        $response = $this->get('/product/'.$product->slug)->assertOk();

        $response->assertSee('Three forms of magnesium.');
        $response->assertSee('Magnesium glycinate 200 mg');
        $response->assertSee('Supports normal muscle function');
        $response->assertSee('Two capsules before bed.');
        $response->assertSee('Do not exceed the stated dose.');
        $response->assertSee('Serving size');
        $response->assertSee('Vegetarian');
        $response->assertSee('Made in United Kingdom');
    }

    public function test_empty_detail_sections_are_left_out_rather_than_filled_with_placeholders(): void
    {
        $product = $this->product(['short_description' => 'Just the basics.']);

        $response = $this->get('/product/'.$product->slug)->assertOk();

        $response->assertSee('Just the basics.');

        // The mockup's filler copy must never reach a customer. Matched on the
        // exact phrases it used, not the bare word - the footer legitimately
        // carries a placeholder postal address.
        $response->assertDontSee('Placeholder description');
        $response->assertDontSee('Placeholder usage guidance');
        $response->assertDontSee('Placeholder ingredient list');
        $response->assertDontSee('Placeholder benefit');

        // An accordion with nothing behind it is not rendered at all.
        $response->assertDontSee('Full ingredient list');
        $response->assertDontSee('Safety and warnings');
    }

    public function test_the_page_invents_no_reviews(): void
    {
        $product = $this->product();

        $response = $this->get('/product/'.$product->slug)->assertOk();

        // There is no reviews table; three glowing quotes would be a fabricated
        // endorsement, not a placeholder.
        $response->assertDontSee('Reviewer one');
        $response->assertSee('No reviews for');
    }

    public function test_the_best_seller_tag_only_appears_on_a_featured_product(): void
    {
        $plain = $this->product(['name' => 'Plain One']);
        $this->get('/product/'.$plain->slug)->assertOk()->assertDontSee('Best seller');

        $featured = $this->product(['name' => 'Featured One', 'is_featured' => true]);
        $this->get('/product/'.$featured->slug)->assertOk()->assertSee('Best seller');
    }

    public function test_the_gallery_falls_back_to_the_default_bottle_without_a_photo(): void
    {
        $product = $this->product();

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('images/defaults/product.svg', false);
    }

    public function test_the_gallery_shows_the_photo_when_there_is_one(): void
    {
        $product = $this->product();
        $product->forceFill(['thumbnail_path' => 'uploads/products/hero.webp'])->save();

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('uploads/products/hero.webp', false);
    }

    /* ------------------------------------------------------------ related */

    public function test_related_products_prefer_the_same_category(): void
    {
        $product = $this->product(['name' => 'The Product']);
        $this->product(['name' => 'Same Category Friend']);

        $other = ProductCategory::factory()->create(['name' => 'Daily Essentials']);
        Product::factory()->create(['category_id' => $other->id, 'name' => 'Different Category']);

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();
        $strip = substr($html, strpos($html, 'Related products'));

        $this->assertStringContainsString('Same Category Friend', $strip);
        // Topped up from elsewhere rather than leaving the grid half empty.
        $this->assertStringContainsString('Different Category', $strip);
    }

    public function test_a_product_never_appears_in_its_own_related_strip(): void
    {
        $product = $this->product(['name' => 'Only Product']);

        $html = $this->get('/product/'.$product->slug)->assertOk()->getContent();

        // With nothing else live the whole strip is omitted.
        $this->assertStringNotContainsString('Related products', $html);
    }

    public function test_related_products_are_published_only(): void
    {
        $product = $this->product(['name' => 'The Product']);
        $this->product(['name' => 'Hidden Friend', 'status' => 'draft']);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertDontSee('Hidden Friend');
    }

    /* --------------------------------------------------------------- meta */

    public function test_the_title_uses_the_products_meta_title_when_set(): void
    {
        $product = $this->product(['name' => 'Plain Name', 'meta_title' => 'Better For Search']);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('<title>Better For Search - NoRx Dose</title>', false);
    }

    public function test_the_title_falls_back_to_the_product_name(): void
    {
        $product = $this->product(['name' => 'Plain Name', 'meta_title' => null]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('<title>Plain Name - NoRx Dose</title>', false);
    }
}
