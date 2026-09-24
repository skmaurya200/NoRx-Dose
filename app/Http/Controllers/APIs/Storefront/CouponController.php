<?php

namespace App\Http\Controllers\APIs\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\CouponService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The codes the storefront may offer, and the one-tap apply behind them.
 *
 * Unauthenticated, so it exposes only what a shopper is allowed to know: the
 * public codes, and whether the basket in front of them qualifies. A private
 * code never appears in the listing but still applies when entered.
 */
class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $coupons,
        private readonly CheckoutService $checkout,
    ) {}

    /**
     * GET /api/storefront/coupons
     *
     * The cart and the checkout render this as a list of "Apply" buttons, so
     * nobody has to know a code exists or type it correctly. The subtotal is
     * a query parameter purely so each card can say how much more is needed -
     * whether the discount is actually granted is decided again, from the real
     * basket, when the order is placed.
     */
    public function index(Request $request): JsonResponse
    {
        $subtotal = max(0, (float) $request->query('subtotal', 0));

        return ApiResponse::success([
            'coupons' => $this->coupons->offers($subtotal),
            'shipping' => $this->checkout->shippingMethods(),
            'free_shipping_over' => config('shop.free_shipping_over'),
            'free_shipping_method' => config('shop.free_shipping_method'),
            'currency_symbol' => config('shop.currency_symbol', '$'),
        ], 'Offers loaded.');
    }

    /**
     * POST /api/storefront/coupons/apply
     *
     * Answers one question - may this basket use this code - from the basket
     * itself rather than from a subtotal the page hands over, so the answer
     * the customer is shown is the answer the order will get.
     */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.pack_label' => ['nullable', 'string', 'max:60'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('shop.max_line_quantity', 99)],
        ]);

        // No coupon and no shipping method: this only needs the honest
        // subtotal, and quote() raises the same friendly errors the order
        // itself would for an unavailable product.
        $quote = $this->checkout->quote($data['items'], null, null);

        // Throws CouponNotApplicableException (422) with the customer-facing
        // reason, which the handler renders through the shared envelope.
        $coupon = $this->coupons->resolve($data['code'], $quote['subtotal']);

        $discount = $coupon->discountFor($quote['subtotal']);

        return ApiResponse::success([
            'coupon' => $this->coupons->describe($coupon, $quote['subtotal']),
            'subtotal' => $quote['subtotal'],
            'discount' => $discount,
        ], 'Code applied — '.$coupon->valueLabel().' on your order.');
    }
}
