<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Moving an order along from the panel.
 *
 * The behaviour worth guarding here is the stock: cancelling an order gives
 * its units back, reinstating it takes them out again, and neither may happen
 * twice however many times the status is flipped.
 */
class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'name' => 'Calm Magnesium Complex',
            'price' => 50.00,
            'track_inventory' => true,
            'stock_quantity' => 20,
        ]);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     */
    private function placeOrder(?array $items = null): Order
    {
        $this->postJson('/api/storefront/checkout', [
            'items' => $items ?? [['product_id' => $this->product->id, 'quantity' => 3]],
            'shipping_method' => 'usps',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '4155550132',
            'street' => '2500 Mission Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'postal_code' => '94110',
            'country' => 'US',
            'card_holder' => 'Ada Lovelace',
            'card_number' => '4242424242424242',
            'card_expiry' => '12 / '.str_pad((string) ((now()->year + 2) % 100), 2, '0', STR_PAD_LEFT),
            'card_cvc' => '123',
        ])->assertStatus(201);

        return Order::latest('id')->firstOrFail();
    }

    private function setStatus(Order $order, string $status)
    {
        return $this->asAdmin()
            ->patchJson('/api/manager/orders/'.$order->id.'/status', ['status' => $status]);
    }

    private function setPayment(Order $order, string $status)
    {
        return $this->asAdmin()
            ->patchJson('/api/manager/orders/'.$order->id.'/payment-status', ['payment_status' => $status]);
    }

    /* -------------------------------------------------------------- default */

    /**
     * Nothing takes money automatically, so nothing may claim it has.
     */
    public function test_a_new_order_is_pending_on_both_counts(): void
    {
        $order = $this->placeOrder();

        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('pending', $order->payment->status);
        $this->assertNull($order->payment->paid_at);
    }

    /* ---------------------------------------------------------------- auth */

    public function test_the_status_endpoints_are_closed_to_guests(): void
    {
        $order = $this->placeOrder();

        $this->patchJson('/api/manager/orders/'.$order->id.'/status', ['status' => 'shipped'])
            ->assertUnauthorized();
    }

    /* --------------------------------------------------------------- status */

    public function test_an_order_status_can_be_changed(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, 'shipped')
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped');

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, 'teleported')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * The money and the goods have both already moved, so reopening a
     * delivered order is almost always a mis-click.
     */
    public function test_a_delivered_order_cannot_be_reopened(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, 'delivered')->assertOk();
        $this->setStatus($order->fresh(), 'processing')->assertStatus(422);

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_a_delivered_order_can_still_be_cancelled(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, 'delivered')->assertOk();
        $this->setStatus($order->fresh(), 'cancelled')->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    /* ---------------------------------------------------------------- stock */

    public function test_cancelling_an_order_puts_its_stock_back(): void
    {
        $order = $this->placeOrder();

        $this->assertSame(17, $this->product->fresh()->stock_quantity);

        $this->setStatus($order, 'cancelled')->assertOk();

        $this->assertSame(20, $this->product->fresh()->stock_quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    /**
     * The guard that matters: flipping through other statuses after a cancel
     * must not hand the stock back a second time.
     */
    public function test_stock_is_never_returned_twice(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, 'cancelled')->assertOk();
        $this->setStatus($order->fresh(), 'cancelled')->assertOk();

        $this->assertSame(20, $this->product->fresh()->stock_quantity);
    }

    public function test_reinstating_a_cancelled_order_takes_the_stock_again(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, 'cancelled')->assertOk();
        $this->assertSame(20, $this->product->fresh()->stock_quantity);

        $this->setStatus($order->fresh(), 'processing')->assertOk();

        $this->assertSame(17, $this->product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    /**
     * Stock lives on the size when a product sells in sizes, which is the same
     * row the checkout took it from.
     */
    public function test_cancelling_returns_stock_to_the_right_size(): void
    {
        // Through the relation: product_id is deliberately not fillable on
        // ProductPack, so a form post can never reassign a size.
        $pack = $this->product->packs()->create([
            'label' => '60 count',
            'price' => 68.00,
            'stock_quantity' => 10,
            'sort_order' => 1,
        ]);

        $order = $this->placeOrder([
            ['product_id' => $this->product->id, 'pack_label' => '60 count', 'quantity' => 4],
        ]);

        $this->assertSame(6, $pack->fresh()->stock_quantity);

        $this->setStatus($order, 'cancelled')->assertOk();

        $this->assertSame(10, $pack->fresh()->stock_quantity);
        // The product's own count was never touched for a pack line.
        $this->assertSame(20, $this->product->fresh()->stock_quantity);
    }

    /* --------------------------------------------------------------- payment */

    public function test_marking_a_payment_paid_stamps_the_time(): void
    {
        $order = $this->placeOrder();

        $this->setPayment($order, 'paid')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $payment = $order->fresh()->payment;

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    /**
     * One event, one action: an order waiting on payment starts moving as soon
     * as the money is marked in.
     */
    public function test_marking_a_payment_paid_starts_a_pending_order(): void
    {
        $order = $this->placeOrder();

        $this->setPayment($order, 'paid')
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_marking_a_payment_paid_does_not_disturb_a_later_status(): void
    {
        $order = $this->placeOrder();

        $this->setStatus($order, 'shipped')->assertOk();
        $this->setPayment($order->fresh(), 'paid')->assertOk();

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_a_refund_clears_the_paid_timestamp(): void
    {
        $order = $this->placeOrder();

        $this->setPayment($order, 'paid')->assertOk();
        $this->setPayment($order->fresh(), 'refunded')->assertOk();

        $payment = $order->fresh()->payment;

        $this->assertSame('refunded', $payment->status);
        $this->assertNull($payment->paid_at);
    }

    public function test_an_unknown_payment_status_is_refused(): void
    {
        $order = $this->placeOrder();

        $this->setPayment($order, 'maybe')
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_status');
    }

    /* --------------------------------------------------------------- screens */

    public function test_the_detail_page_offers_both_pickers(): void
    {
        $order = $this->placeOrder();

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee(route('api.manager.orders.status', $order->id), false)
            ->assertSee(route('api.manager.orders.payment-status', $order->id), false);
    }

    /**
     * Everything an operator needs when someone rings up about a line.
     */
    public function test_the_detail_page_shows_the_product_behind_each_line(): void
    {
        $order = $this->placeOrder();

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Calm Magnesium Complex')
            ->assertSee($this->product->sku)
            ->assertSee($this->product->brand)
            // The quantity and unit price, as the line prints them.
            ->assertSee('3 &times; $50.00', false)
            ->assertSee('$150.00')
            ->assertSee(route('manager.products.show', $this->product->id), false);
    }

    /**
     * The panel shows the whole number, at the operator's request. The
     * grouping is the one printed on the card.
     */
    public function test_the_detail_page_shows_the_full_card_number(): void
    {
        $order = $this->placeOrder();

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('4242 4242 4242 4242', false);
    }

    /**
     * The security code is kept and shown, at the shop owner's instruction.
     */
    public function test_the_security_code_is_stored_and_shown(): void
    {
        $order = $this->placeOrder();

        $this->assertSame('123', $order->payment->card_cvc);

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Security code')
            ->assertDontSee('not stored');
    }

    /**
     * Encrypted at rest like the number beside it: readable through the model,
     * meaningless in a database dump without APP_KEY.
     */
    public function test_the_security_code_is_encrypted_at_rest(): void
    {
        $this->placeOrder();

        $raw = (string) DB::table('tbl_order_payments')->value('card_cvc');

        $this->assertNotSame('123', $raw);
        $this->assertNotEmpty($raw);
    }

    /**
     * It is readable in the panel and nowhere else - not in an API response,
     * and not on the customer's own receipt.
     */
    public function test_the_security_code_never_leaves_the_panel(): void
    {
        $order = $this->placeOrder();

        $this->assertArrayNotHasKey('card_cvc', $order->payment->toArray());

        $this->asAdmin()
            ->getJson('/api/manager/orders/'.$order->id)
            ->assertOk()
            ->assertDontSee('"123"', false);

        $this->get(route('order.confirmation', $order->public_token))
            ->assertOk()
            ->assertDontSee('Security code');
    }

    /**
     * The switch that turns it off again without a code change.
     */
    public function test_the_security_code_is_dropped_when_the_switch_is_off(): void
    {
        config(['shop.store_card_cvc' => false]);

        $order = $this->placeOrder();

        $this->assertNull($order->payment->card_cvc);

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('not stored');
    }

    /**
     * American Express prints its digits four-six-five, so that is how they
     * are grouped rather than forcing them into fours.
     */
    public function test_an_amex_number_is_grouped_the_way_it_is_printed(): void
    {
        $this->postJson('/api/storefront/checkout', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'shipping_method' => 'usps',
            'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'email' => 'ada@example.com', 'phone' => '4155550132',
            'street' => '2500 Mission Street', 'city' => 'San Francisco',
            'state' => 'CA', 'postal_code' => '94110', 'country' => 'US',
            'card_holder' => 'Ada Lovelace',
            'card_number' => '378282246310005',
            'card_expiry' => '12 / '.str_pad((string) ((now()->year + 2) % 100), 2, '0', STR_PAD_LEFT),
            'card_cvc' => '1234',
        ])->assertStatus(201);

        $order = Order::latest('id')->firstOrFail();

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('3782 822463 10005', false);
    }

    public function test_the_list_offers_the_pickers_too(): void
    {
        $order = $this->placeOrder();

        $this->asAdmin()
            ->get('/manager/orders')
            ->assertOk()
            ->assertSee(route('api.manager.orders.status', $order->id), false);
    }
}
