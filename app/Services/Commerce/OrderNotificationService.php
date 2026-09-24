<?php

namespace App\Services\Commerce;

use App\Mail\OrderPlacedMail;
use App\Models\Admin;
use App\Models\Order;
use App\Support\Settings\Site;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the Admin a customer has placed an order.
 *
 * Called after the order is committed and after the customer already has
 * their confirmation (see CheckoutController::place), so a slow or broken
 * mail server can delay nothing and cancel nothing: a failed notification is
 * reported to the log and the order stands.
 */
class OrderNotificationService
{
    public function orderPlaced(Order $order): void
    {
        $recipients = $this->recipients();

        if ($recipients === []) {
            Log::warning('New order notification not sent: no admin email is configured.', [
                'order_number' => $order->order_number,
            ]);

            return;
        }

        try {
            Mail::to($recipients)->send(new OrderPlacedMail(
                $order->loadMissing(['items', 'payment']),
                Site::name(),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * ADMIN_ORDER_EMAIL when it is set; otherwise every active Admin account's
     * own address.
     *
     * @return array<int, string>
     */
    public function recipients(): array
    {
        $configured = array_values(array_filter(
            (array) config('admin.orders.notify'),
            fn (mixed $address) => is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if ($configured !== []) {
            return $configured;
        }

        return Admin::query()
            ->where('role', Admin::ROLE_ADMIN)
            ->where('is_active', true)
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }
}
