<?php

namespace Tests\Feature\Storefront;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cart and checkout pages themselves - what the server renders into them
 * before any JavaScript runs.
 */
class CartCheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cart_renders(): void
    {
        $this->get('/cart')->assertOk()->assertSee('Available offers');
    }

    public function test_the_checkout_renders(): void
    {
        $this->get('/checkout')->assertOk()->assertSee('Billing details');
    }

    /**
     * The offers are written into the page, not fetched, so the cart shows
     * them on first paint.
     */
    public function test_the_cart_carries_the_public_offers(): void
    {
        Coupon::factory()->percent(10, 99)->create(['code' => 'SAVE10']);
        Coupon::factory()->private()->create(['code' => 'SECRET']);

        $response = $this->get('/cart')->assertOk();

        $response->assertSee('SAVE10');
        $response->assertDontSee('SECRET');
    }

    /**
     * The whole reason the coupon input is gone: nothing on either page asks
     * the customer to type a code.
     */
    public function test_neither_page_has_a_coupon_text_box(): void
    {
        foreach (['/cart', '/checkout'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Enter coupon code');
        }
    }

    /* -------------------------------------------------------- US only */

    public function test_the_checkout_offers_only_the_united_states(): void
    {
        $response = $this->get('/checkout')->assertOk();

        $response->assertSee('value="US"', false);
        $response->assertDontSee('United Kingdom');
        $response->assertDontSee('>India<', false);
        $response->assertDontSee('Australia');
    }

    public function test_the_state_picker_lists_us_states_only(): void
    {
        $response = $this->get('/checkout')->assertOk();

        $response->assertSee('value="CA"', false);
        $response->assertSee('California');
        $response->assertSee('New York');
        $response->assertSee('District of Columbia');

        $response->assertDontSee('Uttar Pradesh');
        $response->assertDontSee('Maharashtra');
    }

    /**
     * The page carries the shipping prices the server will charge, so the two
     * cannot drift apart.
     */
    /**
     * A line saved before pictures were carried, or one from a product with no
     * photo, still shows something - the store's default bottle, published for
     * the cart store to fall back to.
     */
    public function test_the_layout_publishes_the_default_cart_image(): void
    {
        $this->get('/cart')
            ->assertOk()
            ->assertSee('default_image', false)
            ->assertSee('images', false);
    }

    public function test_the_layout_publishes_the_shipping_config(): void
    {
        $this->get('/cart')
            ->assertOk()
            ->assertSee('AURUM_SHOP')
            // @json hex-escapes the quotes, so the payload is matched on its
            // values rather than on JSON punctuation.
            ->assertSee('usps')
            ->assertSee('place_order');
    }
}
