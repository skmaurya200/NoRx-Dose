<?php

namespace Tests\Feature\Storefront;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Placing an order.
 *
 * Two things are worth more than the rest here: that the server prices the
 * basket itself, and that the card is stored the way the migration promises -
 * number encrypted, security code nowhere at all.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/storefront/checkout';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'name' => 'Calm Magnesium Complex',
            'price' => 50.00,
            'track_inventory' => true,
            'stock_quantity' => 20,
            'allow_backorder' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'shipping_method' => 'usps',
            'coupon_code' => null,

            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+1 (415) 555-0132',
            'street' => '2500 Mission Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'postal_code' => '94110',
            'country' => 'US',
            'notes' => 'Leave with the concierge.',

            'card_holder' => 'Ada Lovelace',
            'card_number' => '4242424242424242',
            'card_expiry' => '12 / '.str_pad((string) ((now()->year + 2) % 100), 2, '0', STR_PAD_LEFT),
            'card_cvc' => '123',
        ], $overrides);
    }

    /* --------------------------------------------------------- happy path */

    public function test_an_order_is_placed_and_stored(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['order_number', 'redirect']]);

        $order = Order::firstOrFail();

        // 2 x $50 = $100, plus $35 U.S.P.S.
        $this->assertSame('100.00', $order->subtotal);
        $this->assertSame('0.00', $order->discount_total);
        $this->assertSame('35.00', $order->shipping_total);
        $this->assertSame('135.00', $order->grand_total);

        $this->assertSame('Ada', $order->first_name);
        $this->assertSame('CA', $order->state);
        $this->assertSame('US', $order->country);
        // Placed, not paid. There is no payment processor wired up, so an
        // order waits for an operator to confirm the money is in.
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNotNull($order->placed_at);

        $this->assertSame($order->order_number, $response->json('data.order_number'));
    }

    public function test_the_order_lines_snapshot_what_was_bought(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $item = Order::firstOrFail()->items->first();

        $this->assertSame('Calm Magnesium Complex', $item->name);
        $this->assertSame($this->product->sku, $item->sku);
        $this->assertSame('50.00', $item->unit_price);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('100.00', $item->line_total);
    }

    public function test_the_stock_comes_down(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $this->assertSame(18, $this->product->fresh()->stock_quantity);
    }

    /* ------------------------------------------------------------ pricing */

    /**
     * The whole point of pricing server-side.
     */
    public function test_a_price_sent_by_the_browser_is_rejected_outright(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload([
            'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 1]],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.price');
    }

    public function test_the_same_size_twice_in_one_payload_is_merged(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload([
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
                ['product_id' => $this->product->id, 'quantity' => 3],
            ],
        ]))->assertStatus(201);

        $order = Order::firstOrFail();

        $this->assertCount(1, $order->items);
        $this->assertSame(5, $order->items->first()->quantity);
        $this->assertSame('250.00', $order->subtotal);
    }

    public function test_an_unpublished_product_cannot_be_ordered(): void
    {
        $draft = Product::factory()->draft()->create(['category_id' => $this->product->category_id]);

        $this->postJson(self::ENDPOINT, $this->payload([
            'items' => [['product_id' => $draft->id, 'quantity' => 1]],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.product_id');

        $this->assertDatabaseCount('tbl_orders', 0);
    }

    public function test_an_order_beyond_the_stock_on_hand_is_refused(): void
    {
        $this->product->update(['stock_quantity' => 1]);

        $this->postJson(self::ENDPOINT, $this->payload([
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.quantity');

        $this->assertDatabaseCount('tbl_orders', 0);
        $this->assertSame(1, $this->product->fresh()->stock_quantity);
    }

    public function test_a_backordered_product_sells_at_zero_stock(): void
    {
        $this->product->update(['stock_quantity' => 0, 'allow_backorder' => true]);

        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $this->assertDatabaseCount('tbl_orders', 1);
    }

    /* ------------------------------------------------------------- coupons */

    public function test_a_coupon_discounts_the_order_and_is_counted(): void
    {
        $coupon = Coupon::factory()->percent(10, 99)->create(['code' => 'SAVE10']);

        $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'SAVE10']))
            ->assertStatus(201);

        $order = Order::firstOrFail();

        $this->assertSame('SAVE10', $order->coupon_code);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame('10.00', $order->discount_total);
        $this->assertSame('125.00', $order->grand_total);

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    /**
     * A code applied on the cart and then invalidated - or one simply invented
     * in the payload - must not survive into the order.
     */
    public function test_a_code_that_no_longer_qualifies_is_dropped_from_the_order(): void
    {
        Coupon::factory()->percent(10, 500)->create(['code' => 'BIGSPEND']);

        $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'BIGSPEND']))
            ->assertStatus(201);

        $order = Order::firstOrFail();

        $this->assertNull($order->coupon_code);
        $this->assertSame('0.00', $order->discount_total);
        $this->assertSame('135.00', $order->grand_total);
    }

    public function test_an_expired_code_is_not_honoured(): void
    {
        $coupon = Coupon::factory()->percent(50)->expired()->create(['code' => 'GONE']);

        $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'GONE']))
            ->assertStatus(201);

        $this->assertSame('0.00', Order::firstOrFail()->discount_total);
        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_a_code_at_its_redemption_limit_is_not_honoured(): void
    {
        Coupon::factory()->percent(10)->exhausted()->create(['code' => 'SPENT']);

        $this->postJson(self::ENDPOINT, $this->payload(['coupon_code' => 'SPENT']))
            ->assertStatus(201);

        $this->assertSame('0.00', Order::firstOrFail()->discount_total);
    }

    /* ------------------------------------------------- billing validation */

    public function test_every_billing_field_is_required(): void
    {
        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name', 'last_name', 'email', 'phone',
                'street', 'city', 'state', 'postal_code',
                'items', 'shipping_method',
                'card_holder', 'card_number', 'card_expiry', 'card_cvc',
            ]);
    }

    /**
     * Country is not asked of the payload the way the others are: there is
     * exactly one, so an omitted one is filled in rather than refused. Naming
     * a different one is still an error - see the next test.
     */
    public function test_an_omitted_country_defaults_to_the_one_country_we_ship_to(): void
    {
        $payload = $this->payload();
        unset($payload['country']);

        $this->postJson(self::ENDPOINT, $payload)->assertStatus(201);

        $this->assertSame('US', Order::firstOrFail()->country);
    }

    public function test_only_the_united_states_is_accepted(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['country' => 'IN']))
            ->assertStatus(422)
            ->assertJsonPath('errors.country.0', 'We currently ship within the United States only.');
    }

    public function test_the_state_must_be_a_us_state(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['state' => 'UP']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('state');

        $this->postJson(self::ENDPOINT, $this->payload(['state' => 'Uttar Pradesh']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('state');
    }

    public function test_a_lower_cased_state_code_is_accepted(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['state' => 'ny']))->assertStatus(201);

        $this->assertSame('NY', Order::firstOrFail()->state);
    }

    public function test_the_postal_code_must_be_a_us_zip(): void
    {
        foreach (['SW1A 1AA', '9411', '941100', 'abcde'] as $zip) {
            $this->postJson(self::ENDPOINT, $this->payload(['postal_code' => $zip]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('postal_code');
        }

        $this->postJson(self::ENDPOINT, $this->payload(['postal_code' => '94110-1234']))
            ->assertStatus(201);
    }

    public function test_the_email_must_be_an_email(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_an_unknown_shipping_method_is_refused(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['shipping_method' => 'teleport']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_method');
    }

    /* ------------------------------------------------- payment validation */

    public function test_a_card_number_must_pass_luhn(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['card_number' => '4242424242424241']))
            ->assertStatus(422)
            ->assertJsonPath('errors.card_number.0', 'Please enter a valid card number.');
    }

    public function test_an_expired_card_is_refused(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['card_expiry' => '01 / 20']))
            ->assertStatus(422)
            ->assertJsonPath('errors.card_expiry.0', 'That card has expired.');
    }

    public function test_a_malformed_expiry_is_refused(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['card_expiry' => '13/99']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('card_expiry');
    }

    public function test_the_security_code_length_follows_the_scheme(): void
    {
        // Visa: three digits.
        $this->postJson(self::ENDPOINT, $this->payload(['card_cvc' => '1234']))
            ->assertStatus(422)
            ->assertJsonPath('errors.card_cvc.0', 'The security code on this card is 3 digits.');

        // Amex: four.
        $this->postJson(self::ENDPOINT, $this->payload([
            'card_number' => '378282246310005',
            'card_cvc' => '123',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.card_cvc.0', 'The security code on this card is 4 digits.');
    }

    /* ------------------------------------------------------ card at rest */

    public function test_the_card_is_stored_as_brand_and_last_four(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $payment = OrderPayment::firstOrFail();

        $this->assertSame('Visa', $payment->card_brand);
        $this->assertSame('4242', $payment->card_last4);
        $this->assertSame('Ada Lovelace', $payment->card_holder);
        $this->assertSame(12, $payment->exp_month);
        $this->assertSame('Visa •••• 4242', $payment->maskedNumber());
    }

    /**
     * The number is readable through the model and unreadable in the table -
     * which is what the `encrypted` cast is there for.
     */
    public function test_the_card_number_is_encrypted_at_rest(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $this->assertSame('4242424242424242', OrderPayment::firstOrFail()->card_number);

        $raw = DB::table('tbl_order_payments')->value('card_number');

        $this->assertNotSame('4242424242424242', $raw);
        $this->assertStringNotContainsString('4242424242424242', (string) $raw);
    }

    /**
     * The security code is kept, at the shop owner's instruction, and is
     * encrypted at rest exactly like the number beside it - so it is readable
     * through the model and meaningless in a database dump.
     */
    public function test_the_security_code_is_stored_encrypted(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload(['card_cvc' => '987']))->assertStatus(201);

        $payment = OrderPayment::firstOrFail();

        $this->assertSame('987', $payment->card_cvc);

        $raw = (string) DB::table('tbl_order_payments')->value('card_cvc');

        $this->assertNotSame('987', $raw);
        $this->assertStringNotContainsString('987', $raw);
    }

    /**
     * SHOP_STORE_CARD_CVC=false returns the shop to its original behaviour:
     * the code is checked at the door and then dropped.
     */
    public function test_the_security_code_is_dropped_when_switched_off(): void
    {
        config(['shop.store_card_cvc' => false]);

        $this->postJson(self::ENDPOINT, $this->payload(['card_cvc' => '987']))->assertStatus(201);

        $this->assertNull(OrderPayment::firstOrFail()->card_cvc);
    }

    public function test_neither_secret_appears_in_a_response(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $payment = OrderPayment::firstOrFail();

        $this->assertArrayNotHasKey('card_number', $payment->toArray());
        $this->assertArrayNotHasKey('card_cvc', $payment->toArray());
    }

    /* -------------------------------------------------------- confirmation */

    public function test_the_confirmation_page_is_reachable_by_its_token(): void
    {
        $redirect = $this->postJson(self::ENDPOINT, $this->payload())
            ->assertStatus(201)
            ->json('data.redirect');

        $order = Order::firstOrFail();

        $this->get($redirect)
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Calm Magnesium Complex')
            ->assertSee('•••• 4242', false);
    }

    /**
     * The token is why the page is safe to leave unauthenticated - an id in
     * the URL would let anyone read anyone's billing address.
     */
    public function test_the_confirmation_page_cannot_be_reached_by_guessing(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertStatus(201);

        $this->get('/order/1')->assertNotFound();
        $this->get('/order/'.str_repeat('a', 48))->assertNotFound();
    }

    /* --------------------------------------------------------------- quote */

    public function test_the_quote_endpoint_prices_a_basket(): void
    {
        Coupon::factory()->percent(10, 99)->create(['code' => 'SAVE10']);

        $this->postJson(self::ENDPOINT.'/quote', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            'coupon_code' => 'SAVE10',
            'shipping_method' => 'fedex',
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.discount', 10)
            ->assertJsonPath('data.shipping', 50)
            ->assertJsonPath('data.total', 140);
    }
}
