<?php

namespace Tests\Feature\Storefront;

use App\Mail\OrderPlacedMail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The Admin is emailed a full summary whenever a customer places an order.
 */
class OrderNotificationTest extends TestCase
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

    public function test_the_admin_is_emailed_when_an_order_is_placed(): void
    {
        Mail::fake();
        $this->withoutDefer();
        config(['admin.orders.notify' => ['orders@aurumwellness.test']]);

        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $order = Order::query()->sole();

        Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('orders@aurumwellness.test')
            && $mail->order->is($order));
    }

    public function test_the_email_carries_the_whole_order_summary(): void
    {
        Mail::fake();
        $this->withoutDefer();
        config(['admin.orders.notify' => ['orders@aurumwellness.test']]);

        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $order = Order::query()->sole();
        $html = '';

        Mail::assertSent(OrderPlacedMail::class, function (OrderPlacedMail $mail) use (&$html) {
            $html = $mail->render();

            return true;
        });

        foreach ([
            $order->order_number,
            'Ada Lovelace',
            'ada@example.com',
            '+1 (415) 555-0132',
            '2500 Mission Street',
            'Leave with the concierge.',
            'Calm Magnesium Complex',
            '$100.00',  // subtotal: 2 x $50
            '$35.00',   // U.S.P.S.
            '$135.00',  // grand total
            'ending 4242',
            route('manager.orders.show', $order->id),
        ] as $expected) {
            $this->assertStringContainsString(e($expected), $html, "The email is missing {$expected}.");
        }
    }

    public function test_the_email_never_carries_the_card_number_or_security_code(): void
    {
        Mail::fake();
        $this->withoutDefer();
        config(['admin.orders.notify' => ['orders@aurumwellness.test']]);

        $this->postJson(self::ENDPOINT, $this->payload(['card_cvc' => '987']))->assertCreated();

        Mail::assertSent(OrderPlacedMail::class, function (OrderPlacedMail $mail) {
            $html = $mail->render();

            $this->assertStringNotContainsString('4242424242424242', $html);
            $this->assertStringNotContainsString('987', $html);

            return true;
        });
    }

    public function test_customer_text_is_escaped_in_the_email(): void
    {
        Mail::fake();
        $this->withoutDefer();
        config(['admin.orders.notify' => ['orders@aurumwellness.test']]);

        $this->postJson(self::ENDPOINT, $this->payload([
            'notes' => '<script>alert(1)</script> [click](http://evil.test)',
        ]))->assertCreated();

        Mail::assertSent(OrderPlacedMail::class, function (OrderPlacedMail $mail) {
            $html = $mail->render();

            $this->assertStringContainsString('&lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString('href="http://evil.test"', $html);

            return true;
        });
    }

    public function test_without_a_configured_address_it_goes_to_the_active_admin_accounts(): void
    {
        Mail::fake();
        $this->withoutDefer();
        config(['admin.orders.notify' => []]);
        Admin::factory()->admin()->create(['email' => 'owner@aurumwellness.test']);
        Admin::factory()->create(['email' => 'manager@aurumwellness.test']);

        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('owner@aurumwellness.test')
            && ! $mail->hasTo('manager@aurumwellness.test'));
    }

    public function test_an_order_is_still_placed_when_the_email_cannot_be_sent(): void
    {
        Exceptions::fake();
        $this->withoutDefer();
        config(['admin.orders.notify' => ['orders@aurumwellness.test']]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP is down'));

        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $this->assertSame(1, Order::query()->count());
        Exceptions::assertReported(fn (\RuntimeException $exception) => $exception->getMessage() === 'SMTP is down');
    }

    public function test_a_failed_checkout_sends_no_email(): void
    {
        Mail::fake();
        $this->withoutDefer();
        config(['admin.orders.notify' => ['orders@aurumwellness.test']]);

        $this->postJson(self::ENDPOINT, $this->payload(['items' => [['product_id' => $this->product->id, 'quantity' => 999]]]))
            ->assertStatus(422);

        Mail::assertNothingSent();
    }
}
