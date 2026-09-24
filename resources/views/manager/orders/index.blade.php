{{--
    Order list. The two status columns are live pickers that write straight to
    the API - moving an order along is the thing an operator does most, and
    opening each order first would be three clicks for one change.

    Cancelling from here puts the order's stock back, same as on the detail
    page: the rule lives in App\Services\Commerce\OrderService, not in either
    screen.
--}}
@extends('manager.components.layout')

@section('title', 'Orders')

@php
    use App\Support\ManagerQuery;

    $symbol = config('shop.currency_symbol', '$');
    $isTrash = ($filters['trashed'] ?? '') === 'only';
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Orders</h1>
            <p class="ph-sub">
                {{ $orders->total() }} {{ Str::plural('order', $orders->total()) }} &middot;
                every order arrives pending until you mark the payment in
            </p>
        </div>
        <div class="ph-actions">
            @if ($isTrash)
                <a href="{{ route('manager.orders.index') }}" class="btn-ghost">
                    <i class="bi bi-arrow-left"></i> Back to orders
                </a>
            @else
                <a href="{{ ManagerQuery::url('manager.orders.index', ['trashed' => 'only']) }}" class="btn-ghost">
                    <i class="bi bi-trash"></i> Deleted @if ($summary['deleted'])({{ $summary['deleted'] }})@endif
                </a>
                <a href="{{ route('manager.coupons.index') }}" class="btn-ghost">
                    <i class="bi bi-tag"></i> Discounts
                </a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'All orders', 'value' => number_format($summary['total']), 'icon' => 'bi-bag-check', 'tone' => 'icon-dark', 'query' => []],
            ['label' => 'Processing', 'value' => number_format($summary['processing']), 'icon' => 'bi-arrow-repeat', 'tone' => 'icon-gold', 'query' => ['status' => 'processing']],
            ['label' => 'Paid', 'value' => number_format($summary['paid']), 'icon' => 'bi-credit-card', 'tone' => 'icon-dark', 'query' => ['payment_status' => 'paid']],
            ['label' => 'Revenue', 'value' => $symbol.number_format($summary['revenue'], 2), 'icon' => 'bi-graph-up', 'tone' => 'icon-gold', 'query' => []],
        ] as $card)
            <div class="col-6 col-xl-3">
                <a class="stat-card d-block" href="{{ ManagerQuery::url('manager.orders.index', $card['query']) }}">
                    <div class="stat-icon {{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-label">{{ $card['label'] }}</div>
                    <div class="stat-value">{{ $card['value'] }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.orders.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.orders.index">
        @if ($isTrash)
            <input type="hidden" name="trashed" value="only">
        @endif

        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Order number, name or email…">
        </div>

        <div class="field">
            <label class="field-label" for="filterStatus">Status</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">All</option>
                @foreach (\App\Models\Order::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterPayment">Payment</label>
            <select class="field-select" id="filterPayment" name="payment_status">
                <option value="">All</option>
                @foreach (\App\Models\Order::PAYMENT_STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="fb-actions">
            <button type="submit" class="btn-ghost"><i class="bi bi-funnel"></i> Filter</button>
            <button type="button" class="btn-ghost" data-filter-reset>Clear</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" data-list-table @if ($orders->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th class="num">Items</th>
                    <th>Discount</th>
                    <th class="num">Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr data-row>
                        <td class="cell-primary">
                            <div class="min-w-0">
                                <a href="{{ route('manager.orders.show', $order->id) }}" class="cell-title">
                                    {{ $order->order_number }}
                                </a>
                                <span class="cell-meta">{{ $order->placed_at?->format('j M Y, H:i') }}</span>
                            </div>
                        </td>

                        <td data-label="Customer">
                            {{ $order->customerName() }}
                            <span class="cell-meta">{{ $order->email }}</span>
                        </td>

                        <td class="num" data-label="Items">{{ $order->items_count }}</td>

                        <td data-label="Discount">
                            @if ($order->coupon_code)
                                <span class="badge-status badge-warn">{{ $order->coupon_code }}</span>
                                <span class="cell-meta">&minus;{{ $order->money($order->discount_total) }}</span>
                            @else
                                <span class="cell-meta">&mdash;</span>
                            @endif
                        </td>

                        <td class="num" data-label="Total"><b>{{ $order->money($order->grand_total) }}</b></td>

                        {{-- Both are pickers, not badges: moving an order along is
                             the thing an operator does most, and making them open
                             the order first would be three clicks for one change. --}}
                        <td data-label="Payment">
                            <select class="field-select status-select pay-{{ $order->payment_status }}"
                                    data-status-select="{{ route('api.manager.orders.payment-status', $order->id) }}"
                                    data-status-field="payment_status"
                                    data-status-prefix="pay"
                                    aria-label="Payment for {{ $order->order_number }}">
                                @foreach (\App\Models\Order::PAYMENT_STATUSES as $status)
                                    <option value="{{ $status }}" @selected($order->payment_status === $status)>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td data-label="Status">
                            <select class="field-select status-select order-{{ $order->status }}"
                                    data-status-select="{{ route('api.manager.orders.status', $order->id) }}"
                                    data-status-field="status"
                                    data-status-prefix="order"
                                    aria-label="Status for {{ $order->order_number }}">
                                @foreach (\App\Models\Order::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($order->status === $status)>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td class="actions" data-label="Actions">
                            <a href="{{ route('manager.orders.show', $order->id) }}"
                               class="row-btn" title="View order" aria-label="View order">
                                <i class="bi bi-eye"></i>
                            </a>

                            @if ($isTrash)
                                <button type="button" class="row-btn"
                                        data-restore="{{ route('api.manager.orders.restore', $order->id) }}"
                                        title="Restore order" aria-label="Restore order">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            @else
                                {{-- A soft delete: the order leaves this list but its
                                     record of the payment stays, and it can be brought
                                     back from Deleted. Stock is not touched - cancelling
                                     is what hands units back. --}}
                                <button type="button" class="row-btn row-btn--danger"
                                        data-delete="{{ route('api.manager.orders.destroy', $order->id) }}"
                                        data-confirm-title="Delete this order?"
                                        data-confirm-message="{{ $order->order_number }} is filed away rather than destroyed — you can restore it from Deleted. Stock is not returned; cancel the order for that."
                                        title="Delete order" aria-label="Delete order">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($orders->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-bag"></i></div>
            <h3>{{ $isTrash ? 'Nothing deleted' : 'No orders yet' }}</h3>
            <p>
                {{ $isTrash
                    ? 'Orders you delete are kept here so a mis-click can be undone.'
                    : 'Orders placed on the storefront land here the moment they are paid for.' }}
            </p>
        </div>

        @if ($orders->hasPages())
            <div class="table-foot">
                <span>Showing {{ $orders->firstItem() }}&ndash;{{ $orders->lastItem() }} of {{ $orders->total() }}</span>
                {{ $orders->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
