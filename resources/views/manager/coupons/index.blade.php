{{--
    Discount codes. Server-rendered so filters and pagination are sealed links;
    the enable/disable toggle and delete post to the API.
--}}
@extends('manager.components.layout')

@section('title', 'Discounts')

@php
    $symbol = config('shop.currency_symbol', '$');
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Discount codes</h1>
            <p class="ph-sub">
                {{ $coupons->total() }} {{ Str::plural('code', $coupons->total()) }} &middot;
                public codes appear on the cart as a one-tap offer
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.orders.index') }}" class="btn-ghost">
                <i class="bi bi-bag-check"></i> Orders
            </a>
            <a href="{{ route('manager.coupons.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New code
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'All codes', 'value' => $summary['total'], 'icon' => 'bi-tags', 'tone' => 'icon-dark', 'query' => []],
            ['label' => 'Usable now', 'value' => $summary['live'], 'icon' => 'bi-check-circle', 'tone' => 'icon-gold', 'query' => ['status' => 'active']],
            ['label' => 'Shown on cart', 'value' => $summary['public'], 'icon' => 'bi-megaphone', 'tone' => 'icon-dark', 'query' => []],
            ['label' => 'Times redeemed', 'value' => $summary['redeemed'], 'icon' => 'bi-graph-up', 'tone' => 'icon-gold', 'query' => []],
        ] as $card)
            <div class="col-6 col-xl-3">
                <a class="stat-card d-block" href="{{ \App\Support\ManagerQuery::url('manager.coupons.index', $card['query']) }}">
                    <div class="stat-icon {{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-label">{{ $card['label'] }}</div>
                    <div class="stat-value">{{ number_format($card['value']) }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.coupons.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.coupons.index">
        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Code or description…">
        </div>

        <div class="field">
            <label class="field-label" for="filterStatus">Status</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">All</option>
                @foreach (['active' => 'Usable now', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'disabled' => 'Disabled'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterType">Type</label>
            <select class="field-select" id="filterType" name="type">
                <option value="">All types</option>
                <option value="percent" @selected(($filters['type'] ?? '') === 'percent')>Percentage</option>
                <option value="fixed" @selected(($filters['type'] ?? '') === 'fixed')>Fixed amount</option>
            </select>
        </div>

        <div class="fb-actions">
            <button type="submit" class="btn-ghost"><i class="bi bi-funnel"></i> Filter</button>
            <button type="button" class="btn-ghost" data-filter-reset>Clear</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" data-list-table @if ($coupons->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Discount</th>
                    <th>Minimum order</th>
                    <th class="num">Used</th>
                    <th>Window</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coupons as $coupon)
                    <tr data-row>
                        <td class="cell-primary">
                            <div class="min-w-0">
                                <a href="{{ route('manager.coupons.edit', $coupon) }}" class="cell-title">
                                    {{ $coupon->code }}
                                </a>
                                <span class="cell-meta">{{ $coupon->descriptionLabel() }}</span>
                            </div>
                        </td>

                        <td data-label="Discount">
                            <b>{{ $coupon->valueLabel() }}</b>
                            @if ($coupon->max_discount_amount !== null)
                                <span class="cell-meta">
                                    up to {{ $symbol }}{{ number_format((float) $coupon->max_discount_amount, 2) }}
                                </span>
                            @endif
                        </td>

                        <td data-label="Minimum order">
                            @if ((float) $coupon->min_order_amount > 0)
                                {{ $symbol }}{{ number_format((float) $coupon->min_order_amount, 2) }}
                            @else
                                <span class="cell-meta">No minimum</span>
                            @endif
                        </td>

                        <td class="num" data-label="Used">
                            {{ number_format($coupon->used_count) }}@if ($coupon->usage_limit) / {{ number_format($coupon->usage_limit) }}@endif
                        </td>

                        <td data-label="Window">
                            @if ($coupon->starts_at || $coupon->ends_at)
                                <span class="cell-meta">
                                    {{ $coupon->starts_at?->format('j M Y') ?? 'Now' }}
                                    &rarr;
                                    {{ $coupon->ends_at?->format('j M Y') ?? 'No end' }}
                                </span>
                            @else
                                <span class="cell-meta">Always on</span>
                            @endif
                        </td>

                        <td data-label="Status">
                            <span class="badge-status
                                @if ($coupon->isRedeemable()) badge-active
                                @elseif ($coupon->hasExpired() || ! $coupon->is_active) badge-muted
                                @else badge-warn @endif" data-status-badge>
                                {{ $coupon->statusLabel() }}
                            </span>
                            @if (! $coupon->is_public)
                                <span class="badge-status badge-draft ms-1" title="Not advertised on the cart">Private</span>
                            @endif
                        </td>

                        <td class="actions" data-label="Actions">
                            {{-- The shared toggle handler needs this module's
                                 wording, or it would say "Hidden" at a code. --}}
                            <button type="button" class="row-btn"
                                    data-toggle-active="{{ route('api.manager.coupons.toggle', $coupon) }}"
                                    data-on-label="Active" data-off-label="Disabled"
                                    data-on-icon="bi-toggle-on" data-off-icon="bi-toggle-off"
                                    data-on-title="Disable this code" data-off-title="Enable this code"
                                    title="{{ $coupon->is_active ? 'Disable this code' : 'Enable this code' }}"
                                    aria-label="Toggle code">
                                <i class="bi {{ $coupon->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>

                            <a href="{{ route('manager.coupons.edit', $coupon) }}"
                               class="row-btn" title="Edit" aria-label="Edit code">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <button type="button" class="row-btn row-btn--danger"
                                    data-delete="{{ route('api.manager.coupons.destroy', $coupon) }}"
                                    data-confirm-title="Delete this code?"
                                    data-confirm-message="{{ $coupon->code }} will stop working immediately. Orders that already used it keep their discount."
                                    title="Delete" aria-label="Delete code">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($coupons->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-tag"></i></div>
            <h3>No discount codes yet</h3>
            <p>Create one and it appears on the cart as a button customers can tap — no typing required.</p>
            <a href="{{ route('manager.coupons.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New code
            </a>
        </div>

        @if ($coupons->hasPages())
            <div class="table-foot">
                <span>Showing {{ $coupons->firstItem() }}&ndash;{{ $coupons->lastItem() }} of {{ $coupons->total() }}</span>
                {{ $coupons->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
