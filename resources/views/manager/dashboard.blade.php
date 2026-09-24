@extends('manager.components.layout')

@section('title', 'Dashboard')

@push('vendor')
    {{-- jsDelivr, not cdnjs: cdnjs has no chart.umd build at this path and 404s --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@php
    $symbol = config('shop.currency_symbol', '$');
@endphp

@section('content')

    {{-- Welcome banner --}}
    <div class="welcome-banner">
        <div>
            <h1 class="font-serif">{{ $greeting }}, {{ $adminName }}</h1>
            <p>{{ $today }} &mdash; here's how NoRx Dose is performing today.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('manager.analytics') }}" class="btn-outline-cream">View reports</a>
            <a href="{{ route('manager.products.create') }}" class="btn-gold">+ Add product</a>
        </div>
    </div>

    {{-- Stat cards. An Admin gets a fifth - employees signed in right now - and
         all five share one row on a wide screen. The controller passes null to
         everyone else, and the page behind that card checks again. --}}
    @php
        $statColumn = $onlineEmployees !== null ? 'col-6 col-md-4 col-xl' : 'col-6 col-xl-3';
    @endphp

    <div class="row g-3 mb-3">
        @if ($onlineEmployees !== null)
            <div class="{{ $statColumn }}">
                <a class="stat-card d-block" href="{{ route('manager.presence.index') }}"
                   aria-label="{{ $onlineEmployees }} {{ Str::plural('employee', $onlineEmployees) }} signed in now - view details">
                    <div class="stat-icon icon-dark"><i class="bi bi-person-check"></i></div>
                    <div class="stat-label">Employees signed in now</div>
                    <div class="stat-value">{{ number_format($onlineEmployees) }}</div>
                    <div class="stat-trend trend-up">
                        View details <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>
        @endif

        @foreach ($stats as $stat)
            <div class="{{ $statColumn }}">
                <div class="stat-card">
                    <div class="stat-icon {{ $stat['tone'] }}"><i class="bi {{ $stat['icon'] }}"></i></div>
                    <div class="stat-label">{{ $stat['label'] }}</div>
                    <div class="stat-value">{{ $stat['value'] }}</div>
                    <div class="stat-trend {{ $stat['up'] ? 'trend-up' : 'trend-down' }}">
                        <i class="bi bi-arrow-{{ $stat['up'] ? 'up' : 'down' }}-right"></i>
                        {{ $stat['trend'] }} vs last week
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Sales overview</h2>
                        <div class="panel-sub">Revenue across the last 7 days</div>
                    </div>
                    <div class="seg-btns">
                        <button class="active">Week</button>
                        <button>Month</button>
                        <button>Year</button>
                    </div>
                </div>
                <canvas id="salesChart" height="150"
                        data-labels="{{ json_encode($sales['labels']) }}"
                        data-values="{{ json_encode($sales['values']) }}"></canvas>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Order status</h2>
                        <div class="panel-sub">This week's {{ number_format($orderCount) }} orders</div>
                    </div>
                </div>
                <canvas id="statusChart" height="150"
                        data-labels="{{ json_encode(array_column($orderStatus, 'label')) }}"
                        data-values="{{ json_encode(array_column($orderStatus, 'count')) }}"
                        data-colors="{{ json_encode(array_column($orderStatus, 'color')) }}"></canvas>
                <div class="mt-3">
                    @foreach ($orderStatus as $status)
                        <div class="legend-row">
                            <span><span class="legend-dot" style="background:{{ $status['color'] }};"></span>{{ $status['label'] }}</span>
                            <strong>{{ $status['count'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Recent orders + top products --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Recent orders</h2>
                        <div class="panel-sub">Latest transactions from your store</div>
                    </div>
                    <a href="{{ route('manager.orders.index') }}" class="link-gold">View all <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-clean">
                        <thead>
                            <tr><th>Order</th><th>Customer</th><th>Product</th><th>Amount</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td>
                                        <a href="{{ route('manager.orders.show', $order['id']) }}" class="link-gold">
                                            {{ $order['reference'] }}
                                        </a>
                                    </td>
                                    <td class="d-flex align-items-center gap-2">
                                        <span class="cust-avatar" style="background:{{ $order['colour'] }};">{{ Str::substr($order['customer'], 0, 1) }}</span>
                                        {{ $order['customer'] }}
                                    </td>
                                    <td>{{ $order['product'] }}</td>
                                    <td>{{ $symbol }}{{ number_format($order['amount'], 2) }}</td>
                                    <td><span class="badge-status badge-{{ Str::lower($order['status']) }}">{{ $order['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted small py-3">No orders yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Top products</h2>
                        <div class="panel-sub">Best sellers this month</div>
                    </div>
                </div>

                @forelse ($topProducts as $product)
                    <div class="product-row">
                        <div class="product-swatch" style="background:{{ $product['colour'] }};"></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <span class="fw-medium">{{ $product['name'] }}</span>
                                <span class="text-muted small">{{ $product['sold'] }} sold</span>
                            </div>
                            <div class="progress progress-thin mt-1" role="progressbar"
                                 aria-label="{{ $product['name'] }} share of sales"
                                 aria-valuenow="{{ $product['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width:{{ $product['percent'] }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Nothing sold in the last seven days.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Reviews + low stock --}}
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Where the orders came from</h2>
                        <div class="panel-sub">Last 30 days, credited to the last source before the order</div>
                    </div>
                    <a href="{{ route('manager.analytics') }}" class="link-gold">Full report <i class="bi bi-arrow-right"></i></a>
                </div>

                @forelse ($channels as $channel)
                    <div class="product-row">
                        <div class="product-swatch" style="background:{{ $channel['colour'] }};"></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <span class="fw-medium">
                                    {{ $channel['source'] }}
                                    <span class="text-muted small">/ {{ $channel['medium'] }}</span>
                                </span>
                                <span class="text-muted small">
                                    {{ $channel['orders'] }} {{ Str::plural('order', $channel['orders']) }}
                                    &middot; {{ $symbol }}{{ number_format($channel['revenue'], 2) }}
                                </span>
                            </div>
                            <div class="progress progress-thin mt-1" role="progressbar"
                                 aria-label="{{ $channel['source'] }} share of revenue"
                                 aria-valuenow="{{ $channel['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width:{{ $channel['percent'] }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">
                        No attributed orders in the last 30 days. Sources are recorded from the
                        moment a visitor lands, so this fills in as orders come through.
                    </p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Low stock alerts</h2>
                        <div class="panel-sub">Restock soon to avoid stockouts</div>
                    </div>
                </div>

                @forelse ($lowStock as $item)
                    <div class="alert-row">
                        <span class="alert-dot"></span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('manager.products.show', $item['id']) }}" class="fw-medium">
                                    {{ $item['name'] }}
                                </a>
                                <span class="small text-muted">
                                    {{ $item['left'] === 0 ? 'Out of stock' : $item['left'].' left' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Everything is comfortably in stock.</p>
                @endforelse

                <a href="{{ route('manager.products.index') }}" class="btn-gold w-100 text-center mt-3 d-block">Manage inventory</a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">What people search for</h2>
                        <div class="panel-sub">
                            Terms typed into the storefront search box. The ones that found
                            nothing are listed first &mdash; those are either a product to
                            stock, or one named something customers do not call it.
                        </div>
                    </div>
                </div>

                @forelse ($searches as $search)
                    <div class="alert-row">
                        <span class="alert-dot"></span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <a href="{{ \App\Support\SearchLink::url($search['term']) }}"
                                   class="fw-medium" target="_blank" rel="noopener">
                                    {{ $search['term'] }}
                                </a>
                                <span class="small text-muted">
                                    {{ $search['searches'] }} {{ Str::plural('search', $search['searches']) }} &middot;
                                    @if ($search['results'] === 0)
                                        <b>no results</b>
                                    @else
                                        {{ $search['results'] }} {{ Str::plural('result', $search['results']) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Nobody has used the search box yet.</p>
                @endforelse
            </div>
        </div>
    </div>

@endsection
