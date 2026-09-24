<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderAttribution;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * What the panel does with attribution: one order's detail, and the report
 * across all of them.
 */
class AttributionReportTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * An order with attribution already on it. Built directly rather than
     * through the checkout, because these tests are about the reading end.
     *
     * @param  array<string, mixed>  $attribution
     */
    private function order(array $attribution = [], float $total = 100.00, string $status = 'processing'): Order
    {
        $order = new Order([
            'status' => $status,
            'payment_status' => 'paid',
            'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'email' => 'ada@example.com', 'phone' => '4155550132',
            'street' => '2500 Mission Street', 'city' => 'San Francisco',
            'state' => 'CA', 'postal_code' => '94110', 'country' => 'US',
        ]);

        $order->forceFill([
            'order_number' => 'AW-'.Str::upper(Str::random(8)),
            'public_token' => Str::random(48),
            'shipping_method' => 'usps',
            'shipping_method_label' => 'U.S.P.S (2-3 Days)',
            'subtotal' => $total,
            'grand_total' => $total,
            'currency' => 'USD',
            'placed_at' => now(),
        ])->save();

        if ($attribution !== []) {
            $order->attribution()->create($attribution + [
                'first_source' => 'direct', 'first_medium' => 'direct',
                'last_source' => 'direct', 'last_medium' => 'direct',
            ]);
        }

        return $order->fresh(['attribution']);
    }

    /* ------------------------------------------------------- order detail */

    public function test_the_order_page_shows_both_touches(): void
    {
        $order = $this->order([
            'first_source' => 'google', 'first_medium' => 'organic',
            'first_referrer' => 'https://www.google.com/',
            'first_landing_page' => '/product/abc',
            'last_source' => 'example-blog.com', 'last_medium' => 'referral',
            'last_campaign' => 'offpage_seo',
            'last_referrer' => 'https://example-blog.com/article',
            'last_landing_page' => '/product/abc',
        ]);

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Attribution')
            ->assertSee('First touch')
            ->assertSee('google / organic')
            ->assertSee('Last touch')
            ->assertSee('example-blog.com / referral')
            ->assertSee('offpage_seo')
            ->assertSee('https://example-blog.com/article');
    }

    /**
     * Two identical touches need saying once, not twice.
     */
    public function test_a_single_source_order_does_not_repeat_itself(): void
    {
        $order = $this->order([
            'first_source' => 'facebook', 'first_medium' => 'social',
            'last_source' => 'facebook', 'last_medium' => 'social',
        ]);

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('facebook / social')
            ->assertSee('Same source both times')
            ->assertDontSee('Last touch');
    }

    /**
     * Orders placed before tracking existed say so, rather than being shown
     * as direct traffic - which would be a claim the shop cannot make.
     */
    public function test_an_order_without_attribution_says_so(): void
    {
        $order = $this->order();

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('No attribution recorded');
    }

    /**
     * A campaign name is whatever somebody typed into a link, so the panel is
     * checked for escaping even though the value is cleaned on the way in.
     */
    public function test_attribution_values_are_escaped_in_the_panel(): void
    {
        $order = $this->order();

        // Written straight to the table, bypassing the sanitiser, to prove the
        // view escapes rather than relying only on what stored it.
        $order->attribution()->create([
            'first_source' => 'google', 'first_medium' => 'organic',
            'last_source' => 'evil', 'last_medium' => 'referral',
            'last_referrer' => '<script>alert(1)</script>',
        ]);

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    /* ------------------------------------------------------------- report */

    public function test_the_report_is_closed_to_guests(): void
    {
        $this->get('/manager/analytics')->assertRedirect(route('manager.login'));
    }

    public function test_the_report_groups_orders_by_source_and_medium(): void
    {
        $this->order(['last_source' => 'google', 'last_medium' => 'organic'], 200.00);
        $this->order(['last_source' => 'google', 'last_medium' => 'organic'], 100.00);
        $this->order(['last_source' => 'facebook', 'last_medium' => 'social'], 50.00);

        $this->asAdmin()
            ->get('/manager/analytics')
            ->assertOk()
            ->assertSee('google')
            ->assertSee('organic')
            ->assertSee('facebook')
            // Two google orders worth 300 between them.
            ->assertSee('$300.00')
            ->assertSee('$50.00');
    }

    /**
     * A cancelled order is not revenue, and counting it would flatter whatever
     * channel produced it.
     */
    public function test_cancelled_orders_are_left_out(): void
    {
        $this->order(['last_source' => 'google', 'last_medium' => 'organic'], 200.00);
        $this->order(['last_source' => 'google', 'last_medium' => 'organic'], 500.00, 'cancelled');

        $this->asAdmin()
            ->get('/manager/analytics')
            ->assertOk()
            ->assertSee('$200.00')
            ->assertDontSee('$700.00');
    }

    /**
     * The two touches answer different questions, so the report can be asked
     * either one.
     */
    public function test_the_report_can_credit_the_first_touch_instead(): void
    {
        $this->order([
            'first_source' => 'google', 'first_medium' => 'organic',
            'last_source' => 'facebook', 'last_medium' => 'social',
        ], 100.00);

        $this->asAdmin()->get($this->managerUrl('manager.analytics', ['touch' => 'last']))
            ->assertOk()->assertSee('what closed the sale');

        $this->asAdmin()->get($this->managerUrl('manager.analytics', ['touch' => 'first']))
            ->assertOk()->assertSee('how they found us');
    }

    public function test_campaigns_are_reported_separately(): void
    {
        $this->order([
            'last_source' => 'facebook', 'last_medium' => 'social',
            'last_campaign' => 'summer_sale',
        ], 250.00);

        // Untagged, so it must not appear as a blank campaign row.
        $this->order(['last_source' => 'google', 'last_medium' => 'organic'], 90.00);

        $this->asAdmin()
            ->get('/manager/analytics')
            ->assertOk()
            ->assertSee('Campaigns')
            ->assertSee('summer_sale');
    }

    public function test_orders_with_no_attribution_are_counted_out_loud(): void
    {
        $this->order();
        $this->order(['last_source' => 'google', 'last_medium' => 'organic']);

        $this->asAdmin()
            ->get('/manager/analytics')
            ->assertOk()
            ->assertSee('no attribution recorded');
    }

    public function test_the_report_survives_an_empty_shop(): void
    {
        $response = $this->asAdmin()
            ->get('/manager/analytics')
            ->assertOk()
            ->assertSee('No attributed orders in this period.');

        $this->assertSame(4, substr_count($response->getContent(), 'class="panel h-auto'));
    }

    public function test_the_report_can_be_filtered_by_date(): void
    {
        $old = $this->order(['last_source' => 'google', 'last_medium' => 'organic'], 400.00);
        $old->forceFill(['placed_at' => now()->subMonths(2)])->save();

        $this->order(['last_source' => 'facebook', 'last_medium' => 'social'], 60.00);

        $this->asAdmin()
            ->get($this->managerUrl('manager.analytics', ['from' => now()->subWeek()->toDateString()]))
            ->assertOk()
            ->assertSee('$60.00')
            ->assertDontSee('$400.00');
    }

    /**
     * Deleting an order takes its attribution with it - the row describes that
     * order and nothing else.
     */
    public function test_attribution_is_removed_with_its_order(): void
    {
        $order = $this->order(['last_source' => 'google', 'last_medium' => 'organic']);

        $this->assertSame(1, OrderAttribution::count());

        $order->forceDelete();

        $this->assertSame(0, OrderAttribution::count());
    }

    /**
     * A real order, placed through the checkout, reaches the panel with its
     * attribution intact - the whole path in one test.
     */
    public function test_a_real_order_shows_its_source_in_the_panel(): void
    {
        $product = Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'price' => 50.00,
            'stock_quantity' => 20,
        ]);

        $landing = $this->get('/product/'.$product->slug, ['referer' => 'https://www.bing.com/']);
        $cookie = $landing->getCookie(config('shop.attribution.cookie'));

        $this->withCredentials()
            ->withCookies([config('shop.attribution.cookie') => $cookie->getValue()])
            ->postJson('/api/storefront/checkout', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'shipping_method' => 'usps',
                'first_name' => 'Ada', 'last_name' => 'Lovelace',
                'email' => 'ada@example.com', 'phone' => '4155550132',
                'street' => '2500 Mission Street', 'city' => 'San Francisco',
                'state' => 'CA', 'postal_code' => '94110', 'country' => 'US',
                'card_holder' => 'Ada Lovelace',
                'card_number' => '4242424242424242',
                'card_expiry' => '12 / '.str_pad((string) ((now()->year + 2) % 100), 2, '0', STR_PAD_LEFT),
                'card_cvc' => '123',
            ])
            ->assertStatus(201);

        $order = Order::latest('id')->firstOrFail();

        $this->asAdmin()
            ->get('/manager/orders/'.$order->id)
            ->assertOk()
            ->assertSee('bing / organic');

        $this->asAdmin()
            ->get('/manager/analytics')
            ->assertOk()
            ->assertSee('bing');
    }
}
