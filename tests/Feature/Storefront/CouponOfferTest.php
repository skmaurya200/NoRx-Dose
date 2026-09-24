<?php

namespace Tests\Feature\Storefront;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The offers the storefront advertises, and the one-tap apply behind them.
 *
 * The point of the feature: a shopper never types a code. Whatever is listed
 * here is what the cart draws as a button.
 */
class CouponOfferTest extends TestCase
{
    use RefreshDatabase;

    private const LIST = '/api/storefront/coupons';

    private const APPLY = '/api/storefront/coupons/apply';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'price' => 50.00,
            'stock_quantity' => 100,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function basket(int $quantity = 1): array
    {
        return [['product_id' => $this->product->id, 'quantity' => $quantity]];
    }

    /* -------------------------------------------------------------- listing */

    public function test_public_codes_are_listed(): void
    {
        Coupon::factory()->percent(10, 99)->create(['code' => 'SAVE10']);

        $this->getJson(self::LIST)
            ->assertOk()
            ->assertJsonPath('data.coupons.0.code', 'SAVE10')
            ->assertJsonPath('data.coupons.0.value_label', '10% off');
    }

    public function test_a_private_code_is_never_advertised(): void
    {
        Coupon::factory()->private()->create(['code' => 'SECRET']);

        $this->getJson(self::LIST)
            ->assertOk()
            ->assertJsonPath('data.coupons', []);
    }

    public function test_codes_that_cannot_be_used_are_not_listed(): void
    {
        Coupon::factory()->expired()->create(['code' => 'GONE']);
        Coupon::factory()->disabled()->create(['code' => 'OFF']);
        Coupon::factory()->scheduled()->create(['code' => 'SOON']);
        Coupon::factory()->exhausted()->create(['code' => 'SPENT']);

        $this->getJson(self::LIST)
            ->assertOk()
            ->assertJsonPath('data.coupons', []);
    }

    /**
     * Each card has to be able to say "add $X more", which is what makes the
     * minimum-spend rule usable rather than just enforced.
     */
    public function test_a_listing_reports_the_shortfall_against_a_subtotal(): void
    {
        Coupon::factory()->percent(10, 99)->create(['code' => 'SAVE10']);

        $this->getJson(self::LIST.'?subtotal=60')
            ->assertOk()
            ->assertJsonPath('data.coupons.0.qualifies', false)
            ->assertJsonPath('data.coupons.0.shortfall', 39)
            ->assertJsonPath('data.coupons.0.discount', 0);

        $this->getJson(self::LIST.'?subtotal=150')
            ->assertOk()
            ->assertJsonPath('data.coupons.0.qualifies', true)
            ->assertJsonPath('data.coupons.0.shortfall', 0)
            ->assertJsonPath('data.coupons.0.discount', 15);
    }

    public function test_the_listing_carries_the_shipping_options(): void
    {
        $this->getJson(self::LIST)
            ->assertOk()
            ->assertJsonPath('data.shipping.0.id', 'fedex')
            ->assertJsonPath('data.currency_symbol', '$');
    }

    /* ---------------------------------------------------------------- apply */

    public function test_a_qualifying_basket_applies_a_code(): void
    {
        Coupon::factory()->percent(10, 99)->create(['code' => 'SAVE10']);

        // 3 x $50 = $150, comfortably over the $99 minimum.
        $this->postJson(self::APPLY, ['code' => 'SAVE10', 'items' => $this->basket(3)])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 150)
            ->assertJsonPath('data.discount', 15);
    }

    /**
     * The minimum is measured against the basket the server prices, not
     * against a subtotal the page claims - so a page that lies about its
     * total gets no discount.
     */
    public function test_the_minimum_is_measured_against_the_real_basket(): void
    {
        Coupon::factory()->percent(10, 99)->create(['code' => 'SAVE10']);

        $this->postJson(self::APPLY, [
            'code' => 'SAVE10',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                // A page claiming this line is worth $9,999 changes nothing.
                'price' => 9999,
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'Add $49.00 more to use this code.');
    }

    public function test_an_unknown_code_is_refused(): void
    {
        $this->postJson(self::APPLY, ['code' => 'NOPE', 'items' => $this->basket(3)])
            ->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'That code is not valid.');
    }

    public function test_an_expired_code_says_so(): void
    {
        Coupon::factory()->percent(10)->expired()->create(['code' => 'GONE']);

        $this->postJson(self::APPLY, ['code' => 'GONE', 'items' => $this->basket(3)])
            ->assertStatus(422)
            ->assertJsonFragment(['coupon_code' => ['That code expired on '.now()->subDay()->format('j M Y').'.']]);
    }

    public function test_a_fully_redeemed_code_says_so(): void
    {
        Coupon::factory()->percent(10)->exhausted()->create(['code' => 'SPENT']);

        $this->postJson(self::APPLY, ['code' => 'SPENT', 'items' => $this->basket(3)])
            ->assertStatus(422)
            ->assertJsonPath('errors.coupon_code.0', 'That code has been fully redeemed.');
    }

    /**
     * A code the operator chose not to advertise still works when a customer
     * has been given it directly.
     */
    public function test_a_private_code_still_applies_when_entered(): void
    {
        Coupon::factory()->private()->fixed(15, 0)->create(['code' => 'SECRET']);

        $this->postJson(self::APPLY, ['code' => 'SECRET', 'items' => $this->basket(1)])
            ->assertOk()
            ->assertJsonPath('data.discount', 15);
    }
}
