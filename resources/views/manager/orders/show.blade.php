{{--
    One order in full.

    Laid out tight on purpose. This is a reference screen - an operator opens
    it with a customer on the phone - so what matters sits above the fold
    rather than being spread across half-empty panels.

    Two things worth knowing. The status pickers write straight to the API, and
    cancelling an order there also puts its stock back.

    And the payment panel prints the whole card - number, expiry and security
    code - at the shop owner's instruction. Both secrets are encrypted at rest
    and decrypted only here, which means this screen, and any screenshot of it,
    carries everything needed to charge the card. The trade-off is recorded in
    the migration that added the code column.
--}}
@extends('manager.components.layout')

@section('title', 'Order '.$order->order_number)

@php
    $payment = $order->payment;
@endphp

@section('content')
<div class="order-view">

    <div class="page-head page-head--tight">
        <div>
            <h1 class="font-serif">{{ $order->order_number }}</h1>
            <p class="ph-sub">
                {{ $order->placed_at?->format('j M Y, H:i') }}
                &middot; {{ $order->itemCount() }} {{ Str::plural('item', $order->itemCount()) }}
                &middot; <b>{{ $order->money($order->grand_total) }}</b>
                &middot; {{ $order->customerName() }}
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('order.confirmation', $order->public_token) }}"
               class="btn-ghost" target="_blank" rel="noopener">
                <i class="bi bi-receipt"></i> Receipt
            </a>
            <a href="{{ route('manager.orders.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back
            </a>

            {{-- Soft delete only. The order is filed away, not destroyed: a
                 refund or a tax return may still need it. --}}
            @if ($order->trashed())
                <button type="button" class="btn-ghost"
                        data-restore="{{ route('api.manager.orders.restore', $order->id) }}">
                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                </button>
            @else
                <button type="button" class="btn-ghost"
                        data-delete="{{ route('api.manager.orders.destroy', $order->id) }}"
                        data-redirect="{{ route('manager.orders.index') }}"
                        data-confirm-title="Delete this order?"
                        data-confirm-message="{{ $order->order_number }} is filed away rather than destroyed — you can restore it from Deleted. Stock is not returned; cancel the order for that.">
                    <i class="bi bi-trash"></i> Delete
                </button>
            @endif
        </div>
    </div>

    {{-- A strip rather than a panel: two controls do not need 22px of padding
         and a heading of their own. --}}
    <div class="order-bar" data-status-group>
        <label class="order-bar__item">
            <span>Order status</span>
            <select class="field-select status-select order-{{ $order->status }}"
                    data-status-select="{{ route('api.manager.orders.status', $order->id) }}"
                    data-status-field="status"
                    data-status-prefix="order"
                    aria-label="Order status">
                @foreach (\App\Models\Order::STATUSES as $status)
                    <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>

        <label class="order-bar__item">
            <span>Payment</span>
            <select class="field-select status-select pay-{{ $order->payment_status }}"
                    data-status-select="{{ route('api.manager.orders.payment-status', $order->id) }}"
                    data-status-field="payment_status"
                    data-status-prefix="pay"
                    aria-label="Payment status">
                @foreach (\App\Models\Order::PAYMENT_STATUSES as $status)
                    <option value="{{ $status }}" @selected($order->payment_status === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>

        <p class="order-bar__note">
            Saves on change. <b>Cancelled</b> returns the stock; marking payment
            <b>paid</b> moves a pending order into processing.
        </p>
    </div>

    <div class="order-grid">

        {{-- ====================================================== the items --}}
        <div class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Items</h2>
                <span class="badge-status {{ $order->statusBadge() }}" data-status-badge-for="status">
                    {{ ucfirst($order->status) }}
                </span>
            </div>

            @foreach ($order->items as $item)
                <div class="order-line">
                    <div class="order-line__media">
                        @if ($item->product?->thumbnailUrl())
                            <img src="{{ $item->product->thumbnailUrl() }}" alt="{{ $item->name }}" loading="lazy">
                        @else
                            <span class="cell-thumb cell-thumb--empty"><i class="bi bi-box-seam"></i></span>
                        @endif
                    </div>

                    <div class="order-line__body">
                        <div class="order-line__title">
                            @if ($item->product)
                                <a href="{{ route('manager.products.show', $item->product_id) }}">{{ $item->name }}</a>
                            @else
                                {{ $item->name }}
                            @endif

                            @if ($item->pack_label)
                                <span class="badge-status badge-draft">{{ $item->pack_label }}</span>
                            @endif
                        </div>

                        {{-- One wrapped line of dot-separated facts rather than a
                             grid: the set differs per line, and a grid leaves
                             holes wherever a product has no brand or no size. --}}
                        <p class="order-line__facts">
                            @if ($item->sku)<span>{{ $item->sku }}</span>@endif
                            @if ($item->product?->brand)<span>{{ $item->product->brand }}</span>@endif
                            @if ($item->product?->category)<span>{{ $item->product->category->name }}</span>@endif

                            @if ($item->pack)
                                <span>{{ number_format($item->pack->stock_quantity) }} in stock</span>
                                @if ((float) $item->pack->price !== (float) $item->unit_price)
                                    <span class="is-warn">now {{ $order->money($item->pack->price) }}</span>
                                @endif
                            @elseif ($item->product?->track_inventory)
                                <span>{{ number_format($item->product->stock_quantity) }} in stock</span>
                            @endif

                            @unless ($item->product)
                                <span class="is-warn">removed from the catalogue</span>
                            @endunless
                        </p>
                    </div>

                    <div class="order-line__total">
                        {{ $order->money($item->line_total) }}
                        <span>{{ $item->quantity }} &times; {{ $order->money($item->unit_price) }}</span>
                    </div>
                </div>
            @endforeach

            <dl class="kv kv--tight order-totals">
                <div class="kv-row">
                    <dt>Subtotal</dt>
                    <dd>{{ $order->money($order->subtotal) }}</dd>
                </div>

                @if ((float) $order->discount_total > 0)
                    <div class="kv-row">
                        <dt>
                            Discount
                            @if ($order->coupon_code)
                                <span class="badge-status badge-warn">{{ $order->coupon_code }}</span>
                            @endif
                        </dt>
                        <dd>&minus;{{ $order->money($order->discount_total) }}</dd>
                    </div>
                @endif

                <div class="kv-row">
                    <dt>Shipping &mdash; {{ $order->shipping_method_label }}</dt>
                    <dd>{{ (float) $order->shipping_total > 0 ? $order->money($order->shipping_total) : 'Free' }}</dd>
                </div>

                <div class="kv-row kv-row--total">
                    <dt>Total</dt>
                    <dd>{{ $order->money($order->grand_total) }}</dd>
                </div>
            </dl>

            @if ($order->notes)
                <p class="order-note">
                    <b>Customer note</b>
                    {{ $order->notes }}
                </p>
            @endif
        </div>

        {{-- ======================================================= the side --}}
        <div class="order-side">

            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Card &amp; payment</h2>
                    <span class="badge-status {{ $order->paymentBadge() }}" data-status-badge-for="payment_status">
                        {{ ucfirst($order->payment_status) }}
                    </span>
                </div>

                @if ($payment)
                    @if ($payment->fullNumber())
                        <div class="card-face">
                            <div class="card-face__top">
                                <span class="card-face__chip" aria-hidden="true"></span>
                                <span class="card-face__brand">{{ $payment->card_brand ?: 'Card' }}</span>
                            </div>

                            <div class="card-face__number">{{ $payment->fullNumber() }}</div>

                            <div class="card-face__foot">
                                <span class="card-face__cell">
                                    <small>Card holder</small>
                                    {{ $payment->card_holder ?: '—' }}
                                </span>
                                <span class="card-face__cell">
                                    <small>Expires</small>
                                    {{ $payment->expiryLabel() ?? '—' }}
                                    @if ($payment->hasExpired())
                                        <b class="card-face__warn">expired</b>
                                    @endif
                                </span>
                                <span class="card-face__cell">
                                    <small>CVV</small>
                                    @if ($payment->cvcLabel())
                                        <span class="card-face__cvc">{{ $payment->cvcLabel() }}</span>
                                    @else
                                        {{-- Placed before the column existed, or while
                                             SHOP_STORE_CARD_CVC was off. --}}
                                        <span class="card-face__muted">not stored</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Only what the card above does not already say - repeating
                         the brand, the holder and the expiry underneath it was a
                         good part of what made this column so long. --}}
                    <dl class="kv kv--tight">
                        <div class="kv-row">
                            <dt>Charged</dt>
                            <dd>{{ $order->money($payment->amount) }} {{ $payment->currency }}</dd>
                        </div>
                        <div class="kv-row"><dt>Method</dt><dd>{{ ucfirst($payment->method) }}</dd></div>

                        <div class="kv-row">
                            <dt>Security code</dt>
                            <dd class="mono">
                                {{ $payment->cvcLabel() ?? '—' }}
                            </dd>
                        </div>

                        @if ($payment->binLabel())
                            <div class="kv-row"><dt>Issuer (BIN)</dt><dd class="mono">{{ $payment->binLabel() }}</dd></div>
                        @endif

                        <div class="kv-row">
                            <dt>Paid at</dt>
                            <dd>{{ $payment->paid_at?->format('j M Y, H:i') ?? '—' }}</dd>
                        </div>

                        @if ($payment->reference)
                            <div class="kv-row"><dt>Reference</dt><dd class="mono">{{ $payment->reference }}</dd></div>
                        @endif
                    </dl>

                    <p class="field-hint mb-0">
                        <i class="bi bi-shield-lock"></i>
                        Number and code are encrypted in the database and decrypted for this
                        screen only. Everything needed to charge this card is on it &mdash;
                        treat a screenshot the way you would treat the card itself.
                    </p>
                @else
                    <p class="field-hint mb-0">No payment recorded against this order.</p>
                @endif
            </div>

            {{-- Customer and billing were two panels saying one thing about one
                 person, which is a good part of why this column ran so long. --}}
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Customer</h2></div>

                <dl class="kv kv--tight">
                    <div class="kv-row"><dt>Name</dt><dd>{{ $order->customerName() }}</dd></div>
                    <div class="kv-row">
                        <dt>Email</dt>
                        <dd><a href="mailto:{{ $order->email }}">{{ $order->email }}</a></dd>
                    </div>
                    <div class="kv-row">
                        <dt>Phone</dt>
                        <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->phone) }}">{{ $order->phone }}</a></dd>
                    </div>
                </dl>

                <p class="order-address">
                    <b>Billing address</b>
                    {{ $order->street }}<br>
                    {{ $order->city }}, {{ $order->state }} {{ $order->postal_code }}<br>
                    {{ $order->stateName() }} &middot; {{ $order->country }}
                </p>
            </div>

            @include('manager.orders.attribution', ['attribution' => $order->attribution])

            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Audit</h2></div>

                <dl class="kv kv--tight">
                    <div class="kv-row"><dt>Placed</dt><dd>{{ $order->placed_at?->format('j M Y, H:i') ?? '—' }}</dd></div>
                    <div class="kv-row"><dt>Last change</dt><dd>{{ $order->updated_at?->format('j M Y, H:i') }}</dd></div>
                    <div class="kv-row"><dt>From IP</dt><dd class="mono">{{ $order->ip_address ?? '—' }}</dd></div>
                    <div class="kv-row">
                        <dt>Stock</dt>
                        <dd>
                            @if ($order->stock_restored_at)
                                Returned {{ $order->stock_restored_at->format('j M') }}
                            @else
                                Held by this order
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
