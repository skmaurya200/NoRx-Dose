<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The order screens in the admin panel: list and one order in full.
 *
 * HTML only. The two things an operator can change - where the order has got
 * to, and what happened to the money - are posted by the browser to
 * App\Http\Controllers\APIs\OrderController, so the transition rules and the
 * stock adjustments have one implementation.
 */
class OrderController extends Controller
{
    /**
     * GET /manager/orders
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'payment_status', 'trashed', 'per_page']);

        $orders = Order::query()
            ->withCount('items')
            // Deleted orders are filed, not destroyed, so the list can be
            // pointed at them to undo a mis-click.
            ->when(($filters['trashed'] ?? '') === 'only', fn (Builder $q) => $q->onlyTrashed())
            ->search($filters['search'] ?? null)
            ->when(($filters['status'] ?? '') !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(($filters['payment_status'] ?? '') !== '',
                fn (Builder $q) => $q->where('payment_status', $filters['payment_status']))
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters))
            ->withQueryString();

        return view('manager.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'summary' => $this->summary(),
        ]);
    }

    /**
     * GET /manager/orders/{order}
     */
    public function show(int $order): View
    {
        // Bound by id rather than by route key: the model resolves on its
        // public token for the storefront confirmation page, which is not what
        // the panel links to.
        $found = Order::query()
            // withTrashed so a deleted order can still be opened and restored
            // rather than only ever being a row that vanished.
            ->withTrashed()
            ->with([
                'payment',
                'attribution',
                'coupon:id,code',
                // The line's own snapshot is what the receipt shows; the live
                // product is loaded alongside it so the panel can offer a
                // thumbnail, a link and today's stock. Either may be missing -
                // a product can be deleted long after it was sold.
                'items.product:id,name,slug,sku,brand,thumbnail_path,track_inventory,stock_quantity,status,category_id',
                'items.product.category:id,name',
                'items.pack:id,product_id,label,price,stock_quantity',
            ])
            ->findOrFail($order);

        return view('manager.orders.show', ['order' => $found]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return [
            'total' => Order::query()->count(),
            'processing' => Order::query()->where('status', 'processing')->count(),
            'paid' => Order::query()->where('payment_status', 'paid')->count(),
            'revenue' => (float) Order::query()->where('payment_status', 'paid')->sum('grand_total'),
            'deleted' => Order::onlyTrashed()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(5, min(100, (int) ($filters['per_page'] ?? 20)));
    }
}
