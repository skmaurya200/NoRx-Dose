<?php

namespace App\Services\Commerce;

use App\Exceptions\Commerce\CheckoutFailedException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPack;
use Illuminate\Support\Facades\DB;

/**
 * What happens to an order after it has been placed.
 *
 * The two rules that matter here, and the reason this is a service rather than
 * a couple of lines in a controller:
 *
 *  - cancelling an order puts its stock back, exactly once, however many times
 *    the status is flipped afterwards;
 *  - reinstating a cancelled order takes that stock out again.
 *
 * Get either wrong and the catalogue quietly drifts away from what is on the
 * shelf, which is the kind of bug nobody notices until they oversell.
 */
class OrderService
{
    /**
     * Move an order along.
     *
     * @throws CheckoutFailedException
     */
    public function updateStatus(Order $order, string $status): Order
    {
        if (! in_array($status, Order::STATUSES, true)) {
            throw new CheckoutFailedException('That is not a status an order can have.');
        }

        if ($order->status === $status) {
            return $order;
        }

        // A delivered order is finished. Reopening one is almost always a
        // mis-click, and the money and the stock have both already moved.
        if ($order->status === 'delivered' && $status !== 'cancelled') {
            throw new CheckoutFailedException(
                'This order is already delivered. Cancel it if something went wrong.',
            );
        }

        return DB::transaction(function () use ($order, $status) {
            $was = $order->status;

            $order->status = $status;

            if ($status === 'cancelled') {
                $this->restoreStock($order);
            } elseif ($was === 'cancelled') {
                // Coming back from cancelled: the stock that was handed back
                // has to be taken out again or the shop is counting it twice.
                $this->takeStock($order);
            }

            $order->save();

            return $order->refresh();
        });
    }

    /**
     * Record what happened to the money.
     *
     * @throws CheckoutFailedException
     */
    public function updatePaymentStatus(Order $order, string $status): Order
    {
        if (! in_array($status, Order::PAYMENT_STATUSES, true)) {
            throw new CheckoutFailedException('That is not a payment status an order can have.');
        }

        return DB::transaction(function () use ($order, $status) {
            $order->payment_status = $status;

            // An order sitting at "pending" was waiting for exactly this, so
            // marking the money in starts the fulfilment clock rather than
            // leaving the operator to change two dropdowns for one event.
            if ($status === 'paid' && $order->status === 'pending') {
                $order->status = 'processing';
            }

            $order->save();

            $payment = $order->payment;

            if ($payment !== null) {
                $payment->status = $status;
                $payment->paid_at = $status === 'paid' ? ($payment->paid_at ?? now()) : null;
                $payment->save();
            }

            return $order->refresh()->load('payment');
        });
    }

    /* --------------------------------------------------------------- filing */

    /**
     * Takes an order off the panel's list without destroying it.
     *
     * A soft delete only - an order is the record of a payment and of what was
     * shipped, and there are refunds, chargebacks and tax returns that need it
     * long after an operator has decided they are done looking at it.
     *
     * Stock is deliberately untouched. Cancelling is what hands units back;
     * deleting is filing, and doing both from one button would silently move
     * inventory an operator did not ask to move.
     */
    public function delete(Order $order): void
    {
        $order->delete();
    }

    public function restore(Order $order): Order
    {
        $order->restore();

        return $order->refresh();
    }

    /* ---------------------------------------------------------------- stock */

    /**
     * Puts a cancelled order's units back on the shelf.
     *
     * Guarded by stock_restored_at rather than by the status itself: an order
     * can be cancelled, reinstated and cancelled again, and each of those has
     * to move the stock exactly one way.
     */
    private function restoreStock(Order $order): void
    {
        if ($order->stock_restored_at !== null) {
            return;
        }

        foreach ($order->items()->get() as $item) {
            $this->moveStock($item, +$item->quantity);
        }

        $order->stock_restored_at = now();
    }

    private function takeStock(Order $order): void
    {
        if ($order->stock_restored_at === null) {
            return;
        }

        foreach ($order->items()->get() as $item) {
            $this->moveStock($item, -$item->quantity);
        }

        $order->stock_restored_at = null;
    }

    /**
     * Adjusts whichever row actually holds the stock - the size when the
     * product sells in sizes, the product itself when it does not. The same
     * rule CheckoutService used on the way in, so the two cannot disagree.
     *
     * A product that no longer tracks inventory, or has since been deleted, is
     * skipped rather than treated as an error: the order still stands.
     */
    private function moveStock(OrderItem $item, int $delta): void
    {
        if ($item->pack_id !== null) {
            $pack = ProductPack::find($item->pack_id);

            if ($pack !== null && $pack->product?->track_inventory) {
                // Clamped at zero because the column is unsigned; a backordered
                // line has nothing to give back below that.
                $pack->stock_quantity = max(0, (int) $pack->stock_quantity + $delta);
                $pack->save();
            }

            return;
        }

        if ($item->product_id === null) {
            return;
        }

        $product = Product::find($item->product_id);

        if ($product === null || ! $product->track_inventory) {
            return;
        }

        $product->stock_quantity = max(0, (int) $product->stock_quantity + $delta);
        $product->save();
    }
}
