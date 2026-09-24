<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\Commerce\CouponService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The discount screens in the admin panel: list and create/edit form.
 *
 * HTML only - every write is posted by the browser to
 * App\Http\Controllers\APIs\CouponController, so the rules and their messages
 * have one implementation.
 */
class CouponController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    /**
     * GET /manager/coupons
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'type', 'per_page']);

        return view('manager.coupons.index', [
            'coupons' => $this->coupons->paginate($filters),
            'filters' => $filters,
            'summary' => $this->summary(),
        ]);
    }

    /**
     * GET /manager/coupons/create
     */
    public function create(): View
    {
        return view('manager.coupons.form', [
            'coupon' => new Coupon([
                'type' => 'percent',
                'min_order_amount' => 0,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 0,
            ]),
            'isEdit' => false,
        ]);
    }

    /**
     * GET /manager/coupons/{coupon}/edit
     */
    public function edit(Coupon $coupon): View
    {
        return view('manager.coupons.form', [
            'coupon' => $coupon,
            'isEdit' => true,
        ]);
    }

    /**
     * Counters for the cards above the list.
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'total' => Coupon::query()->count(),
            'live' => Coupon::query()->redeemable()->count(),
            'public' => Coupon::query()->redeemable()->public()->count(),
            'redeemed' => (int) Coupon::query()->sum('used_count'),
        ];
    }
}
