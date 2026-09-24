<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order screens in the panel. Orders are placed through the real checkout
 * rather than a factory, so these also prove the two halves fit together.
 */
class OrderScreenTest extends TestCase
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
            'stock_quantity' => 40,
        ]);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function placeOrder(?string $code = null): Order
    {
        $this->postJson('/api/storefront/checkout', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
            'shipping_method' => 'usps',
            'coupon_code' => $code,
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

    public function test_the_order_screens_are_closed_to_guests(): void
    {
        $this->get('/manager/orders')->assertRedirect(route('manager.login'));
    }

    public function test_the_list_shows_a_placed_order(): void
    {
        $order = $this->placeOrder();

        $this->asAdmin()
            ->get('/manager/orders')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('ada@example.com')
            ->assertSee('$185.00');
    }

    public function test_an_order_can_be_opened_in_full(): void
    {
        Coupon::factory()->percent(10, 99)->create(['code' => 'SAVE10']);

        $order = $this->placeOrder('SAVE10');

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Calm Magnesium Complex')
            ->assertSee('SAVE10')
            ->assertSee('Ada Lovelace')
            ->assertSee('California')
            ->assertSee('94110');
    }

    /**
     * The panel prints the card in full; the customer's own confirmation page
     * still only gets the last four.
     */
    public function test_the_order_page_shows_the_card_and_the_receipt_does_not(): void
    {
        $order = $this->placeOrder();

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('4242 4242 4242 4242', false);

        $this->get(route('order.confirmation', $order->public_token))
            ->assertOk()
            ->assertSee('•••• 4242', false)
            ->assertDontSee('4242 4242 4242 4242', false);
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $order = $this->placeOrder();

        // Placed, not paid: an order starts at pending on both counts.
        $this->asAdmin()
            ->get($this->managerUrl('manager.orders.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->asAdmin()
            ->get($this->managerUrl('manager.orders.index', ['status' => 'cancelled']))
            ->assertOk()
            ->assertDontSee($order->order_number);
    }

    public function test_an_unknown_order_is_a_404(): void
    {
        $this->asAdmin()->get('/manager/orders/999')->assertNotFound();
    }

    /* -------------------------------------------------- delete and restore */

    public function test_an_order_is_soft_deleted_and_restored(): void
    {
        $order = $this->placeOrder();

        $this->asAdmin()
            ->deleteJson('/api/manager/orders/'.$order->id)
            ->assertOk();

        $this->assertSoftDeleted($order);

        // Gone from the list, still reachable on its own page so it can be
        // brought back.
        $this->asAdmin()
            ->get('/manager/orders')
            ->assertOk()
            ->assertDontSee($order->order_number);

        $this->asAdmin()
            ->get($this->managerUrl('manager.orders.index', ['trashed' => 'only']))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->asAdmin()
            ->patchJson('/api/manager/orders/'.$order->id.'/restore')
            ->assertOk();

        $this->assertNull($order->fresh()->deleted_at);
    }

    /**
     * Deleting is filing, not cancelling. Moving the stock as a side effect
     * would be inventory an operator did not ask to move.
     */
    public function test_deleting_an_order_leaves_the_stock_alone(): void
    {
        $order = $this->placeOrder();
        $after = $this->product->fresh()->stock_quantity;

        $this->asAdmin()->deleteJson('/api/manager/orders/'.$order->id)->assertOk();

        $this->assertSame($after, $this->product->fresh()->stock_quantity);
    }

    public function test_deleting_an_order_is_closed_to_guests(): void
    {
        $order = $this->placeOrder();

        $this->deleteJson('/api/manager/orders/'.$order->id)->assertUnauthorized();
    }
}
