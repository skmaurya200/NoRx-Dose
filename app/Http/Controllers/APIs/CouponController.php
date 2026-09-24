<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Coupon\StoreCouponRequest;
use App\Http\Requests\APIs\Coupon\UpdateCouponRequest;
use App\Http\Resources\APIs\CouponResource;
use App\Models\Coupon;
use App\Services\Commerce\CouponService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Discount code module API - the back-office half.
 *
 * The storefront reads codes through APIs\Storefront\CouponController, which
 * is unauthenticated and only ever exposes public, redeemable ones.
 */
class CouponController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    /**
     * GET /api/manager/coupons
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->coupons->paginate($request->only([
            'search', 'status', 'type', 'per_page',
        ]));

        return ApiResponse::success([
            'items' => CouponResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Coupons loaded.');
    }

    /**
     * GET /api/manager/coupons/{coupon}
     */
    public function show(Coupon $coupon): JsonResponse
    {
        return ApiResponse::success(
            new CouponResource($coupon->loadCount('orders')),
            'Coupon loaded.',
        );
    }

    /**
     * POST /api/manager/coupons
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->coupons->create($request->payload());

        return ApiResponse::success(
            new CouponResource($coupon),
            'Coupon created.',
            Response::HTTP_CREATED,
        );
    }

    /**
     * POST /api/manager/coupons/{coupon}
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon = $this->coupons->update($coupon, $request->payload());

        return ApiResponse::success(new CouponResource($coupon), 'Coupon updated.');
    }

    /**
     * DELETE /api/manager/coupons/{coupon}
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->coupons->delete($coupon);

        return ApiResponse::success(null, 'Coupon deleted.');
    }

    /**
     * PATCH /api/manager/coupons/{coupon}/toggle
     */
    public function toggle(Coupon $coupon): JsonResponse
    {
        $coupon = $this->coupons->toggleActive($coupon);

        return ApiResponse::success(
            new CouponResource($coupon),
            $coupon->is_active ? 'Coupon is now active.' : 'Coupon is now disabled.',
        );
    }
}
