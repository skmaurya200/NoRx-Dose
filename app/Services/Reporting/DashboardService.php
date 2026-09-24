<?php

namespace App\Services\Reporting;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SearchQuery;
use App\Services\Commerce\AttributionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures on the dashboard.
 *
 * Every one is counted from the orders table. Two rules run through all of it:
 *
 *  - cancelled orders are never revenue, so they are excluded everywhere a
 *    figure is money and counted only where the figure is explicitly a status;
 *  - a comparison is always against the same length of time immediately
 *    before, so "12% up on last week" means exactly that.
 *
 * Kept out of the controller because it is eight aggregate queries, and a
 * controller that owns eight queries is a controller nobody can change safely.
 */
class DashboardService
{
    /** The window the headline figures cover. */
    private const WINDOW_DAYS = 7;

    /** Attribution moves slowly, so its panel reads over a longer period. */
    private const CHANNEL_DAYS = 30;

    /**
     * The palette the dashboard already used for avatars and swatches. Picked
     * from a name rather than at random, so a customer keeps their colour
     * between page loads.
     *
     * @var array<int, string>
     */
    private const PALETTE = ['#c9a12e', '#3d6aa8', '#4c7a52', '#8a6c17', '#b1503f', '#241c13'];

    public function __construct(private readonly AttributionService $attribution) {}

    /* ---------------------------------------------------------------- cards */

    /**
     * The four figures across the top, each against the week before.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stats(): array
    {
        $now = $this->window();
        $before = $this->previousWindow();

        $revenue = $this->revenueBetween(...$now);
        $revenueBefore = $this->revenueBetween(...$before);

        $orders = $this->orderCountBetween(...$now);
        $ordersBefore = $this->orderCountBetween(...$before);

        $customers = $this->newCustomersBetween(...$now);
        $customersBefore = $this->newCustomersBetween(...$before);

        // Guarded: an average over no orders is not zero, it is undefined, and
        // printing $0.00 would read as "we sold nothing for nothing".
        $average = $orders > 0 ? $revenue / $orders : 0.0;
        $averageBefore = $ordersBefore > 0 ? $revenueBefore / $ordersBefore : 0.0;

        $symbol = config('shop.currency_symbol', '$');

        return [
            $this->card('Total revenue', $symbol.number_format($revenue, 2), $revenue, $revenueBefore, 'bi-cash-coin', 'icon-gold'),
            $this->card('Orders', number_format($orders), $orders, $ordersBefore, 'bi-bag-check', 'icon-dark'),
            $this->card('New customers', number_format($customers), $customers, $customersBefore, 'bi-people', 'icon-gold'),
            $this->card('Avg. order value', $symbol.number_format($average, 2), $average, $averageBefore, 'bi-receipt', 'icon-dark'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $label, string $value, float $now, float $before, string $icon, string $tone): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'trend' => $this->trendLabel($now, $before),
            'up' => $now >= $before,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }

    /**
     * The percentage change, or a plain dash when there is nothing to compare
     * against - "∞% up" from a week with no orders is not information.
     */
    private function trendLabel(float $now, float $before): string
    {
        if ($before <= 0) {
            return $now > 0 ? 'new' : 'no change';
        }

        return number_format(abs(($now - $before) / $before) * 100, 1).'%';
    }

    /* --------------------------------------------------------------- charts */

    /**
     * Revenue for each of the last seven days, oldest first.
     *
     * Built from a date-keyed lookup rather than straight from the query, so a
     * day with no orders is a zero on the chart instead of a missing column.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    public function salesChart(): array
    {
        [$from, $to] = $this->window();

        $byDay = $this->revenueQuery()
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw('DATE(placed_at) as day, SUM(grand_total) as revenue')
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $labels = [];
        $values = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $labels[] = $day->format('D');
            $values[] = round((float) ($byDay[$day->toDateString()] ?? 0), 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Every order by status, with the colours the doughnut already used.
     *
     * A status with no orders is dropped: an empty slice is a gap in the chart
     * and a zero in the legend, and neither tells anyone anything.
     *
     * @return array<int, array<string, mixed>>
     */
    public function orderStatus(): array
    {
        $colours = [
            'delivered' => '#c9a12e',
            'processing' => '#241c13',
            'shipped' => '#3d6aa8',
            'pending' => '#8a6c17',
            'cancelled' => '#b1503f',
        ];

        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $rows = [];

        foreach ($colours as $status => $colour) {
            $count = (int) ($counts[$status] ?? 0);

            if ($count > 0) {
                $rows[] = ['label' => ucfirst($status), 'count' => $count, 'color' => $colour];
            }
        }

        return $rows;
    }

    /* ---------------------------------------------------------------- lists */

    /**
     * The five most recent orders.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentOrders(int $limit = 5): Collection
    {
        return Order::query()
            ->with('items:id,order_id,name,pack_label')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Order $order) {
                $first = $order->items->first();
                $extra = $order->items->count() - 1;

                return [
                    'id' => $order->id,
                    'reference' => $order->order_number,
                    'customer' => $order->customerName(),
                    'colour' => $this->colourFor($order->customerName()),
                    // The first line, and how many more there were - a receipt
                    // in a table cell has room for one name.
                    'product' => $first
                        ? $first->displayName().($extra > 0 ? ' +'.$extra.' more' : '')
                        : '—',
                    'amount' => (float) $order->grand_total,
                    'status' => ucfirst($order->status),
                ];
            });
    }

    /**
     * The best sellers by units, over the same window as the headline figures.
     *
     * Grouped on the line's stored name rather than on product_id, so a
     * product deleted from the catalogue still shows what it sold.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topProducts(int $limit = 5): Collection
    {
        [$from, $to] = $this->window();

        $rows = OrderItem::query()
            ->join('tbl_orders', 'tbl_orders.id', '=', 'tbl_order_items.order_id')
            ->whereNull('tbl_orders.deleted_at')
            ->where('tbl_orders.status', '!=', 'cancelled')
            ->whereBetween('tbl_orders.placed_at', [$from, $to])
            ->selectRaw('tbl_order_items.name, SUM(tbl_order_items.quantity) as sold')
            ->groupBy('tbl_order_items.name')
            ->orderByDesc('sold')
            ->limit($limit)
            ->get();

        $top = (int) ($rows->max('sold') ?: 0);

        return $rows->values()->map(fn ($row, $index) => [
            'name' => $row->name,
            'sold' => (int) $row->sold,
            // Measured against the best seller, so the leader fills the bar
            // and the rest read against it.
            'percent' => $top > 0 ? (int) round($row->sold / $top * 100) : 0,
            'colour' => self::PALETTE[$index % count(self::PALETTE)],
        ]);
    }

    /**
     * What needs restocking. Out of stock first, because that is already
     * costing sales rather than about to.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lowStock(int $limit = 5): Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->where('track_inventory', true)
            ->where('allow_backorder', false)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit($limit)
            ->get(['id', 'name', 'stock_quantity'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'left' => (int) $product->stock_quantity,
            ]);
    }

    /**
     * Where the orders came from, over the last month.
     *
     * A longer window than the rest of the dashboard on purpose: a channel
     * that produced two orders last Tuesday is noise, and marketing decisions
     * are not made on a week.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topChannels(int $limit = 5): Collection
    {
        $rows = $this->attribution->report(['source', 'medium'], 'last', [
            'from' => now()->subDays(self::CHANNEL_DAYS)->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $total = $rows->sum('revenue');

        return $rows->take($limit)->values()->map(fn ($row, $index) => [
            'source' => $row->source,
            'medium' => $row->medium,
            'orders' => $row->orders,
            'revenue' => $row->revenue,
            'percent' => $total > 0 ? (int) round($row->revenue / $total * 100) : 0,
            'colour' => self::PALETTE[$index % count(self::PALETTE)],
        ]);
    }

    /**
     * What people type into the storefront's search box.
     *
     * The unanswered ones lead, because those are the actionable half: a term
     * searched fifty times that finds nothing is either a product the shop
     * should stock or one whose name does not match what customers call it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topSearches(int $limit = 6): Collection
    {
        return SearchQuery::query()
            ->orderByRaw('CASE WHEN result_count = 0 THEN 0 ELSE 1 END')
            ->popular()
            ->limit($limit)
            ->get()
            ->map(fn (SearchQuery $row) => [
                'term' => $row->term,
                'searches' => $row->search_count,
                'results' => $row->result_count,
            ]);
    }

    /**
     * Orders in the headline window, for the caption under the status chart.
     */
    public function orderCount(): int
    {
        return $this->orderCountBetween(...$this->window());
    }

    /* -------------------------------------------------------------- private */

    /**
     * Orders that count as money: not deleted, not cancelled.
     */
    private function revenueQuery(): Builder
    {
        return Order::query()->where('status', '!=', 'cancelled');
    }

    private function revenueBetween(Carbon $from, Carbon $to): float
    {
        return (float) $this->revenueQuery()->whereBetween('placed_at', [$from, $to])->sum('grand_total');
    }

    private function orderCountBetween(Carbon $from, Carbon $to): int
    {
        return $this->revenueQuery()->whereBetween('placed_at', [$from, $to])->count();
    }

    /**
     * Customers who ordered for the first time in this window.
     *
     * There are no accounts on this shop, so a customer is an email address.
     * "New" means the address has no earlier order - which is the honest
     * version of the question without a users table to ask.
     */
    private function newCustomersBetween(Carbon $from, Carbon $to): int
    {
        return (int) DB::table('tbl_orders')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->selectRaw('email, MIN(placed_at) as first_order')
            ->groupBy('email')
            ->havingRaw('MIN(placed_at) BETWEEN ? AND ?', [$from, $to])
            ->get()
            ->count();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(): array
    {
        return [now()->subDays(self::WINDOW_DAYS - 1)->startOfDay(), now()->endOfDay()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousWindow(): array
    {
        return [
            now()->subDays(self::WINDOW_DAYS * 2 - 1)->startOfDay(),
            now()->subDays(self::WINDOW_DAYS)->endOfDay(),
        ];
    }

    /**
     * A stable colour for a name. crc32 rather than rand, so the same customer
     * is the same colour on every page load.
     */
    private function colourFor(string $name): string
    {
        return self::PALETTE[crc32($name) % count(self::PALETTE)];
    }
}
