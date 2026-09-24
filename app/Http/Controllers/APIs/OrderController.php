<?php

namespace App\Http\Controllers\APIs;

use App\Http\Controllers\Controller;
use App\Http\Resources\APIs\OrderResource;
use App\Models\Order;
use App\Services\Commerce\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Order module API - the back-office half.
 *
 * The two things an operator changes after an order is placed - where it has
 * got to, and what happened to the money - plus filing one away. Everything
 * else on an order is a record of what the customer did and is deliberately
 * not editable.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * GET /api/manager/orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($order->load(['items', 'payment', 'coupon:id,code'])),
            'Order loaded.',
        );
    }

    /**
     * PATCH /api/manager/orders/{order}/status
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);

        // Throws CheckoutFailedException (422) for a move that is not allowed;
        // cancelling here also puts the order's stock back.
        $order = $this->orders->updateStatus($order, $data['status']);

        return ApiResponse::success(
            new OrderResource($order->load(['items', 'payment'])),
            'Order is now '.$order->status.'.',
        );
    }

    /**
     * PATCH /api/manager/orders/{order}/payment-status
     */
    public function updatePaymentStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(Order::PAYMENT_STATUSES)],
        ]);

        $order = $this->orders->updatePaymentStatus($order, $data['payment_status']);

        return ApiResponse::success(
            new OrderResource($order->load(['items', 'payment'])),
            'Payment marked as '.$order->payment_status.'.',
        );
    }

    /**
     * DELETE /api/manager/orders/{order}
     *
     * A soft delete: the order leaves the list but the record of the payment
     * stays, because a refund or a tax return may still need it.
     */
    public function destroy(Order $order): JsonResponse
    {
        $this->orders->delete($order);

        return ApiResponse::success(null, 'Order '.$order->order_number.' moved to deleted.');
    }

    /**
     * PATCH /api/manager/orders/{order}/restore
     */
    public function restore(int $order): JsonResponse
    {
        $found = Order::onlyTrashed()->findOrFail($order);

        $found = $this->orders->restore($found);

        return ApiResponse::success(
            new OrderResource($found->load(['items', 'payment'])),
            'Order '.$found->order_number.' restored.',
        );
    }
}
