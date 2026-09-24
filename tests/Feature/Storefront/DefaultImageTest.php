<?php

namespace Tests\Feature\Storefront;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\DefaultImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pictures the storefront falls back to.
 *
 * The property this file exists to hold: no page ever shows an empty image
 * slot. A product without a photo, a post without a cover and a page whose
 * hero has never been uploaded all get a drawing instead of a hole.
 */
class DefaultImageTest extends TestCase
{
    use RefreshDatabase;

    /* --------------------------------------------------------------- files */

    /**
     * A path to a file that is not there would be worse than no fallback at
     * all, so every slot is checked against the disk.
     */
    public function test_every_default_image_exists_on_disk(): void
    {
        foreach (['hero', 'portrait', 'lab', 'consult', 'article', 'product'] as $slot) {
            $path = parse_url(DefaultImage::for($slot), PHP_URL_PATH);

            $this->assertFileExists(public_path($path), $slot.' is missing');
        }
    }

    /**
     * An unknown slot returns the bottle rather than a broken path.
     */
    public function test_an_unknown_slot_falls_back_to_the_product_image(): void
    {
        $this->assertSame(DefaultImage::product(), DefaultImage::for('nonsense'));
    }

    /* ----------------------------------------------------------- storefront */

    public function test_the_home_page_shows_a_picture_in_every_slot(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('images/defaults/hero.svg', false);
        $response->assertSee('images/defaults/portrait.svg', false);
        $response->assertSee('images/defaults/consult.svg', false);
        $response->assertSee('images/defaults/lab.svg', false);
    }

    public function test_a_product_card_without_a_photo_shows_the_default_bottle(): void
    {
        Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'thumbnail_path' => null,
        ]);

        $this->get('/shop')->assertOk()->assertSee('images/defaults/product.svg', false);
    }

    public function test_a_post_without_a_cover_shows_the_journal_default(): void
    {
        BlogPost::factory()->create(['cover_path' => null]);

        $this->get('/blogs')->assertOk()->assertSee('images/defaults/article.svg', false);
    }

    public function test_a_post_page_without_a_cover_still_has_one(): void
    {
        $post = BlogPost::factory()->create(['cover_path' => null]);

        $this->get('/blogs/'.$post->slug)
            ->assertOk()
            ->assertSee('images/defaults/article.svg', false);
    }

    /**
     * The empty boxes the design used to draw are gone from every page.
     */
    public function test_no_storefront_page_renders_an_empty_placeholder(): void
    {
        Product::factory()->create(['category_id' => ProductCategory::factory()]);
        BlogPost::factory()->create();

        foreach (['/', '/shop', '/all-products', '/blogs', '/about', '/faq', '/reviews'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('data-ph=', false);
        }
    }
}
