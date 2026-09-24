{{--
    New order notification for the Admin. Every value is escaped - most of
    them were typed by the customer. No card number, expiry or security code.
--}}
@php
    $payment = $order->payment;
    $cell = 'padding:8px 10px;border-bottom:1px solid #ece4d2;font-size:14px;color:#2b2620;';
    $label = 'padding:6px 10px;font-size:13px;color:#7a6f5d;width:38%;vertical-align:top;';
    $value = 'padding:6px 10px;font-size:14px;color:#2b2620;vertical-align:top;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New order {{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#f7f3ea;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;">
    <tr>
        <td style="background:#2b2620;padding:18px 24px;color:#e9cf7d;font-size:18px;font-weight:bold;">
            {{ $siteName }} &middot; New order
        </td>
    </tr>
    <tr>
        <td style="padding:22px 24px 6px;">
            <p style="margin:0 0 6px;font-size:20px;font-weight:bold;color:#2b2620;">
                Order {{ $order->order_number }} &mdash; {{ $order->money((float) $order->grand_total) }}
            </p>
            <p style="margin:0;font-size:13px;color:#7a6f5d;">
                Placed {{ $order->placed_at?->format('j M Y, H:i T') }} &middot;
                Status: {{ ucfirst($order->status) }} &middot; Payment: {{ ucfirst($order->payment_status) }}
            </p>
        </td>
    </tr>

    {{-- Customer --}}
    <tr>
        <td style="padding:16px 24px 0;">
            <p style="margin:0 0 6px;font-size:15px;font-weight:bold;color:#2b2620;">Customer</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="{{ $label }}">Name</td><td style="{{ $value }}">{{ $order->customerName() }}</td></tr>
                <tr><td style="{{ $label }}">Email</td><td style="{{ $value }}">{{ $order->email }}</td></tr>
                <tr><td style="{{ $label }}">Phone</td><td style="{{ $value }}">{{ $order->phone }}</td></tr>
                <tr><td style="{{ $label }}">Address</td><td style="{{ $value }}">{{ $order->addressLine() }}</td></tr>
                @if (filled($order->notes))
                    <tr><td style="{{ $label }}">Notes</td><td style="{{ $value }}white-space:pre-line;">{{ $order->notes }}</td></tr>
                @endif
            </table>
        </td>
    </tr>

    {{-- Items --}}
    <tr>
        <td style="padding:18px 24px 0;">
            <p style="margin:0 0 6px;font-size:15px;font-weight:bold;color:#2b2620;">
                Items ({{ $order->itemCount() }})
            </p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr style="background:#f7f3ea;">
                    <th align="left" style="{{ $cell }}font-size:12px;color:#7a6f5d;">Product</th>
                    <th align="right" style="{{ $cell }}font-size:12px;color:#7a6f5d;">Qty</th>
                    <th align="right" style="{{ $cell }}font-size:12px;color:#7a6f5d;">Price</th>
                    <th align="right" style="{{ $cell }}font-size:12px;color:#7a6f5d;">Total</th>
                </tr>
                @foreach ($order->items as $item)
                    <tr>
                        <td style="{{ $cell }}">
                            {{ $item->name }}
                            @if (filled($item->pack_label))
                                <br><span style="font-size:12px;color:#7a6f5d;">{{ $item->pack_label }}</span>
                            @endif
                        </td>
                        <td align="right" style="{{ $cell }}">{{ $item->quantity }}</td>
                        <td align="right" style="{{ $cell }}">{{ $order->money((float) $item->unit_price) }}</td>
                        <td align="right" style="{{ $cell }}">{{ $order->money((float) $item->line_total) }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>

    {{-- Totals --}}
    <tr>
        <td style="padding:12px 24px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="{{ $label }}">Subtotal</td><td align="right" style="{{ $value }}">{{ $order->money((float) $order->subtotal) }}</td></tr>
                @if ((float) $order->discount_total > 0)
                    <tr>
                        <td style="{{ $label }}">Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</td>
                        <td align="right" style="{{ $value }}">&minus;{{ $order->money((float) $order->discount_total) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="{{ $label }}">Shipping{{ $order->shipping_method_label ? ' ('.$order->shipping_method_label.')' : '' }}</td>
                    <td align="right" style="{{ $value }}">{{ (float) $order->shipping_total > 0 ? $order->money((float) $order->shipping_total) : 'Free' }}</td>
                </tr>
                <tr>
                    <td style="{{ $label }}font-weight:bold;color:#2b2620;">Grand total</td>
                    <td align="right" style="{{ $value }}font-weight:bold;font-size:16px;">{{ $order->money((float) $order->grand_total) }}</td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Payment: brand and last four only --}}
    <tr>
        <td style="padding:16px 24px 0;">
            <p style="margin:0 0 6px;font-size:15px;font-weight:bold;color:#2b2620;">Payment</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="{{ $label }}">Method</td>
                    <td style="{{ $value }}">
                        {{ ucfirst($payment?->method ?? 'card') }}@if ($payment?->card_brand) &middot; {{ $payment->card_brand }}@endif
                        @if ($payment?->card_last4) ending {{ $payment->card_last4 }}@endif
                    </td>
                </tr>
                <tr><td style="{{ $label }}">Payment status</td><td style="{{ $value }}">{{ ucfirst($order->payment_status) }}</td></tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:22px 24px 26px;">
            <a href="{{ route('manager.orders.show', $order->id) }}"
               style="display:inline-block;background:#c9a12e;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:11px 20px;border-radius:8px;">
                Open the order in the admin panel
            </a>
            <p style="margin:14px 0 0;font-size:12px;color:#7a6f5d;">
                Card numbers and security codes are never included in this email.
            </p>
        </td>
    </tr>
</table>
</body>
</html>
