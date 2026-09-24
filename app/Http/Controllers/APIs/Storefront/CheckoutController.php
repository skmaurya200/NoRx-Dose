<?php

namespace App\Http\Controllers\APIs\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\APIs\Checkout\PlaceOrderRequest;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\OrderNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Placing an order.
 *
 * Nothing here trusts the payload beyond "which product, which size, how
 * many": PlaceOrderRequest decides whether the form is well-formed, and
 * CheckoutService prices it from the catalogue and writes it.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    /**
     * POST /api/storefront/checkout/quote
     *
     * What this basket costs, according to the server. The pages compute the
     * same figures locally so the totals do not flicker on every quantity
     * change; this is what they reconcile against before the customer commits.
     */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.config('shop.max_order_lines', 50)],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.pack_label' => ['nullable', 'string', 'max:60'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('shop.max_line_quantity', 99)],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'shipping_method' => ['nullable', 'string', 'max:40'],
        ]);

        $quote = $this->checkout->quote(
            $data['items'],
            $data['coupon_code'] ?? null,
            $data['shipping_method'] ?? null,
        );

        return ApiResponse::success($this->present($quote), 'Cart priced.');
    }

    /**
     * POST /api/storefront/checkout
     */
    public function place(PlaceOrderRequest $request, OrderNotificationService $notifications): JsonResponse
    {
        $order = $this->checkout->place($request->payload());

        // After the response has gone to the customer: the Admin's email must
        // never slow the checkout down or turn a placed order into an error.
        defer(fn () => $notifications->orderPlaced($order));

        return ApiResponse::success([
            'order_number' => $order->order_number,
            // Where the browser is sent next. The token, not the id, so the
            // confirmation page cannot be walked by incrementing a number.
            'redirect' => route('order.confirmation', $order->public_token),
        ], 'Order placed.', Response::HTTP_CREATED);
    }

    /**
     * The quote without the Eloquent models it carries internally.
     *
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function present(array $quote): array
    {
        return [
            'lines' => array_map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'name' => $line['name'],
                'pack_label' => $line['pack_label'],
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'line_total' => $line['line_total'],
            ], $quote['lines']),
            'units' => $quote['units'],
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'shipping' => $quote['shipping'],
            'total' => $quote['total'],
            'shipping_is_free' => $quote['shipping_is_free'],
            'shipping_method' => $quote['shipping_method']['id'],
            'coupon_code' => $quote['coupon']?->code,
        ];
    }
}
