<?php

namespace Tests\Feature\Manager;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SearchQuery;
use App\Services\Reporting\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The dashboard, counted from the orders table.
 *
 * Every figure on it used to be a hardcoded array. These hold it to the real
 * numbers - including on a brand new shop, where the honest answer to most of
 * them is zero.
 */
class DashboardTest extends TestCase
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
     * An order, placed whenever and worth whatever.
     *
     * @param  array<string, mixed>  $attribution
     */
    private function order(
        float $total = 100.00,
        ?string $when = null,
        string $status = 'processing',
        string $email = 'ada@example.com',
        array $attribution = [],
    ): Order {
        $order = new Order([
            'status' => $status,
            'payment_status' => 'paid',
            'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'email' => $email, 'phone' => '4155550132',
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
            'placed_at' => $when ? now()->parse($when) : now(),
        ])->save();

        if ($attribution !== []) {
            $order->attribution()->create($attribution);
        }

        return $order;
    }

    private function line(Order $order, string $name, int $quantity = 1, float $price = 50.00): void
    {
        $order->items()->create([
            'name' => $name,
            'sku' => 'AW-'.Str::upper(Str::random(6)),
            'unit_price' => $price,
            'quantity' => $quantity,
            'line_total' => $price * $quantity,
        ]);
    }

    private function service(): DashboardService
    {
        return app(DashboardService::class);
    }

    /* ---------------------------------------------------------- empty shop */

    /**
     * The state every shop starts in. It used to show $24,850 of invented
     * revenue on day one.
     */
    public function test_a_new_shop_shows_zeroes_rather_than_invented_figures(): void
    {
        $this->asAdmin()
            ->get('/manager')
            ->assertOk()
            ->assertSee('$0.00')
            ->assertSee('No orders yet.')
            ->assertSee('Nothing sold in the last seven days.')
            ->assertDontSee('24,850')
            ->assertDontSee('Riya Kapoor');
    }

    /* --------------------------------------------------------------- money */

    public function test_revenue_and_orders_are_counted_from_real_orders(): void
    {
        $this->order(200.00);
        $this->order(150.00);

        $stats = collect($this->service()->stats())->keyBy('label');

        $this->assertSame('$350.00', $stats['Total revenue']['value']);
        $this->assertSame('2', $stats['Orders']['value']);
        $this->assertSame('$175.00', $stats['Avg. order value']['value']);
    }

    /**
     * A cancelled order is not revenue. Counting it would flatter every figure
     * on the page.
     */
    public function test_cancelled_orders_are_not_revenue(): void
    {
        $this->order(200.00);
        $this->order(800.00, status: 'cancelled');

        $stats = collect($this->service()->stats())->keyBy('label');

        $this->assertSame('$200.00', $stats['Total revenue']['value']);
        $this->assertSame('1', $stats['Orders']['value']);
    }

    /** Only the last seven days count towards the headline figures. */
    public function test_older_orders_fall_outside_the_window(): void
    {
        $this->order(100.00);
        $this->order(500.00, when: '-30 days');

        $stats = collect($this->service()->stats())->keyBy('label');

        $this->assertSame('$100.00', $stats['Total revenue']['value']);
    }

    /**
     * The trend compares against the same length of time immediately before,
     * so the wording under each card means what it says.
     */
    public function test_the_trend_compares_against_the_week_before(): void
    {
        $this->order(200.00);
        $this->order(100.00, when: '-10 days');

        $stats = collect($this->service()->stats())->keyBy('label');

        // 200 against 100 is up 100%.
        $this->assertTrue($stats['Total revenue']['up']);
        $this->assertSame('100.0%', $stats['Total revenue']['trend']);
    }

    /**
     * Nothing to compare against is said plainly rather than as an infinite
     * percentage.
     */
    public function test_a_first_week_reads_as_new_rather_than_infinite(): void
    {
        $this->order(200.00);

        $stats = collect($this->service()->stats())->keyBy('label');

        $this->assertSame('new', $stats['Total revenue']['trend']);
    }

    /**
     * There are no accounts on this shop, so a customer is an email address
     * and "new" means it has no earlier order.
     */
    public function test_new_customers_counts_first_time_email_addresses(): void
    {
        $this->order(email: 'returning@example.com', when: '-30 days');
        $this->order(email: 'returning@example.com');
        $this->order(email: 'brandnew@example.com');

        $stats = collect($this->service()->stats())->keyBy('label');

        $this->assertSame('1', $stats['New customers']['value']);
    }

    /* -------------------------------------------------------------- charts */

    public function test_the_sales_chart_covers_seven_days_including_quiet_ones(): void
    {
        $this->order(120.00);

        $chart = $this->service()->salesChart();

        $this->assertCount(7, $chart['labels']);
        $this->assertCount(7, $chart['values']);
        // Today is the last column, and the quiet days before it are zeroes
        // rather than gaps.
        $this->assertSame(120.0, end($chart['values']));
        $this->assertSame(0.0, $chart['values'][0]);
    }

    public function test_the_status_chart_counts_real_statuses(): void
    {
        $this->order(status: 'processing');
        $this->order(status: 'processing');
        $this->order(status: 'shipped');

        $statuses = collect($this->service()->orderStatus())->keyBy('label');

        $this->assertSame(2, $statuses['Processing']['count']);
        $this->assertSame(1, $statuses['Shipped']['count']);
        // A status nobody is in is left off rather than drawn as an empty slice.
        $this->assertFalse($statuses->has('Delivered'));
    }

    /* --------------------------------------------------------------- lists */

    public function test_recent_orders_are_the_real_latest_ones(): void
    {
        $old = $this->order(when: '-2 days');
        $new = $this->order();
        $this->line($new, 'Calm Magnesium Complex');

        $this->asAdmin()
            ->get('/manager')
            ->assertOk()
            ->assertSee($new->order_number)
            ->assertSee($old->order_number)
            ->assertSee('Calm Magnesium Complex')
            ->assertSee(route('manager.orders.show', $new->id), false);
    }

    /** An order of several lines names the first and counts the rest. */
    public function test_an_order_of_several_lines_says_how_many(): void
    {
        $order = $this->order();
        $this->line($order, 'Calm Magnesium Complex');
        $this->line($order, 'Daily Greens Blend');
        $this->line($order, 'Omega-3 Fish Oil');

        $this->asAdmin()->get('/manager')->assertOk()->assertSee('+2 more');
    }

    public function test_top_products_are_counted_from_order_lines(): void
    {
        $one = $this->order();
        $this->line($one, 'Calm Magnesium Complex', 5);
        $this->line($one, 'Daily Greens Blend', 2);

        $two = $this->order();
        $this->line($two, 'Calm Magnesium Complex', 3);

        $top = $this->service()->topProducts();

        $this->assertSame('Calm Magnesium Complex', $top[0]['name']);
        $this->assertSame(8, $top[0]['sold']);
        $this->assertSame(100, $top[0]['percent']);
        $this->assertSame('Daily Greens Blend', $top[1]['name']);
    }

    public function test_low_stock_lists_real_products(): void
    {
        $category = ProductCategory::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Nearly Gone',
            'track_inventory' => true,
            'stock_quantity' => 2,
            'low_stock_threshold' => 10,
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Plenty Left',
            'track_inventory' => true,
            'stock_quantity' => 400,
            'low_stock_threshold' => 10,
        ]);

        $this->asAdmin()
            ->get('/manager')
            ->assertOk()
            ->assertSee('Nearly Gone')
            ->assertSee('2 left')
            ->assertDontSee('Plenty Left');
    }

    /**
     * A backordered product is meant to sell past zero, so it is not an alert.
     */
    public function test_a_backordered_product_is_not_a_stock_alert(): void
    {
        Product::factory()->create([
            'category_id' => ProductCategory::factory(),
            'name' => 'Always Available',
            'track_inventory' => true,
            'allow_backorder' => true,
            'stock_quantity' => 0,
            'low_stock_threshold' => 10,
        ]);

        $this->asAdmin()->get('/manager')->assertOk()->assertDontSee('Always Available');
    }

    /* --------------------------------------------------------- attribution */

    /**
     * The panel that replaced the invented reviews.
     */
    public function test_the_dashboard_shows_where_the_orders_came_from(): void
    {
        $this->order(300.00, attribution: [
            'first_source' => 'google', 'first_medium' => 'organic',
            'last_source' => 'google', 'last_medium' => 'organic',
        ]);

        $this->order(100.00, attribution: [
            'first_source' => 'facebook', 'first_medium' => 'social',
            'last_source' => 'facebook', 'last_medium' => 'social',
        ]);

        $this->asAdmin()
            ->get('/manager')
            ->assertOk()
            ->assertSee('Where the orders came from')
            ->assertSee('google')
            ->assertSee('organic')
            ->assertSee('facebook')
            ->assertSee('$300.00')
            ->assertSee(route('manager.analytics'), false);
    }

    /** Share is measured against the whole period, largest first. */
    public function test_channels_are_ordered_by_revenue(): void
    {
        $this->order(100.00, attribution: ['last_source' => 'facebook', 'last_medium' => 'social']);
        $this->order(400.00, attribution: ['last_source' => 'google', 'last_medium' => 'organic']);

        $channels = $this->service()->topChannels();

        $this->assertSame('google', $channels[0]['source']);
        $this->assertSame(80, $channels[0]['percent']);
        $this->assertSame('facebook', $channels[1]['source']);
    }

    /** Nothing invented while there is nothing to show. */
    public function test_the_channel_panel_is_honest_when_empty(): void
    {
        $this->order(100.00);

        $this->asAdmin()
            ->get('/manager')
            ->assertOk()
            ->assertSee('No attributed orders in the last 30 days.');
    }

    /**
     * Nothing that used to be hardcoded is still on the page.
     */
    public function test_no_invented_data_remains(): void
    {
        $this->order();

        $response = $this->asAdmin()->get('/manager')->assertOk();

        foreach ([
            'Riya Kapoor', 'Sameer Nair', 'Meera Iyer', 'Karan Shah',
            'Night Recovery Tea', '24,850', 'AW-3021', 'Recent reviews',
        ] as $invented) {
            $response->assertDontSee($invented);
        }
    }

    /* --------------------------------------------------------- search terms */

    /**
     * The unanswered terms lead: a search run fifty times that finds nothing
     * is the actionable half of that table.
     */
    public function test_the_dashboard_lists_what_people_search_for(): void
    {
        SearchQuery::factory()->create([
            'term' => 'collagen', 'search_count' => 40, 'result_count' => 6,
        ]);
        SearchQuery::factory()->unanswered()->create([
            'term' => 'protein powder', 'search_count' => 3,
        ]);

        $response = $this->asAdmin()->get('/manager')->assertOk();

        $response->assertSee('What people search for');
        $response->assertSee('protein powder');
        $response->assertSee('collagen');
        $response->assertSeeInOrder(['protein powder', 'collagen']);
    }

    public function test_the_search_panel_says_so_when_nobody_has_searched(): void
    {
        $this->asAdmin()
            ->get('/manager')
            ->assertOk()
            ->assertSee('Nobody has used the search box yet.');
    }
}
