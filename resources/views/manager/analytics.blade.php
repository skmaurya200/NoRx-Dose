{{--
    Where the orders came from.

    Built on attribution recorded against each order, so every figure is a real
    order rather than a tracking script's estimate. Cancelled orders are left
    out of all of it.
--}}
@extends('manager.components.layout')

@section('title', 'Analytics')

@php
    use App\Support\Attribution;

    $symbol = config('shop.currency_symbol', '$');
    $touch = $filters['touch'];

    $money = fn (float $amount) => $symbol.number_format($amount, 2);

    // Every row's share of the total, for the bar behind it. Guarded because
    // a shop with no orders yet would otherwise divide by zero.
    $share = fn (float $value) => $totals['revenue'] > 0
        ? round($value / $totals['revenue'] * 100)
        : 0;
@endphp

@section('content')

    <div class="page-head page-head--tight">
        <div>
            <h1 class="font-serif">Analytics</h1>
            <p class="ph-sub">
                Which channels bring in the orders.
                {{ $touch === 'first' ? 'Credited to how the customer first found the shop.' : 'Credited to what brought them back to buy.' }}
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.orders.index') }}" class="btn-ghost">
                <i class="bi bi-bag-check"></i> Orders
            </a>
        </div>
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.analytics') }}">
        @csrf
        <input type="hidden" name="target" value="manager.analytics">
        <div class="field">
            <label class="field-label" for="touch">Credit</label>
            <select class="field-select" id="touch" name="touch">
                <option value="last" @selected($touch === 'last')>Last touch — what closed the sale</option>
                <option value="first" @selected($touch === 'first')>First touch — how they found us</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="from">From</label>
            <input type="date" class="field-input" id="from" name="from" value="{{ $filters['from'] }}">
        </div>

        <div class="field">
            <label class="field-label" for="to">To</label>
            <input type="date" class="field-input" id="to" name="to" value="{{ $filters['to'] }}">
        </div>

        <div class="fb-actions">
            <button type="submit" class="btn-ghost"><i class="bi bi-funnel"></i> Apply</button>
            <button type="button" class="btn-ghost" data-filter-reset>Clear</button>
        </div>
    </form>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'Attributed orders', 'value' => number_format($totals['orders']), 'icon' => 'bi-bag-check', 'tone' => 'icon-dark'],
            ['label' => 'Attributed revenue', 'value' => $money($totals['revenue']), 'icon' => 'bi-cash-coin', 'tone' => 'icon-gold'],
            ['label' => 'Channels', 'value' => number_format($channels->count()), 'icon' => 'bi-signpost-split', 'tone' => 'icon-dark'],
            ['label' => 'Campaigns', 'value' => number_format($campaigns->count()), 'icon' => 'bi-megaphone', 'tone' => 'icon-gold'],
        ] as $card)
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon {{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-label">{{ $card['label'] }}</div>
                    <div class="stat-value">{{ $card['value'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($untracked > 0)
        {{-- Orders from before tracking existed. Named rather than folded into
             "direct", which would be a claim the shop cannot support. --}}
        <p class="field-hint mb-3">
            <i class="bi bi-info-circle"></i>
            {{ number_format($untracked) }} {{ Str::plural('order', $untracked) }}
            {{ $untracked === 1 ? 'has' : 'have' }} no attribution recorded and
            {{ $untracked === 1 ? 'is' : 'are' }} left out of these figures.
        </p>
    @endif

    <div class="panel h-auto mb-3">
        <div class="panel-head">
            <h2 class="panel-title">Source and medium</h2>
            <span class="cell-meta">Ordered by revenue</span>
        </div>

        @include('manager.partials.attribution-table', [
            'rows' => $channels,
            'columns' => ['Source' => 'source', 'Medium' => 'medium'],
            'empty' => 'No attributed orders in this period.',
        ])
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="panel h-auto">
                <div class="panel-head"><h2 class="panel-title">By source</h2></div>

                @include('manager.partials.attribution-table', [
                    'rows' => $sources,
                    'columns' => ['Source' => 'source'],
                    'empty' => 'Nothing to show yet.',
                ])
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel h-auto">
                <div class="panel-head"><h2 class="panel-title">By medium</h2></div>

                @include('manager.partials.attribution-table', [
                    'rows' => $mediums,
                    'columns' => ['Medium' => 'medium'],
                    'empty' => 'Nothing to show yet.',
                ])
            </div>
        </div>
    </div>

    <div class="panel h-auto mt-3">
        <div class="panel-head">
            <h2 class="panel-title">Campaigns</h2>
            <span class="cell-meta">Orders that arrived on a tagged link</span>
        </div>

        @include('manager.partials.attribution-table', [
            'rows' => $campaigns,
            'columns' => ['Campaign' => 'campaign', 'Source' => 'source', 'Medium' => 'medium'],
            'empty' => 'No tagged campaigns have produced an order yet.',
        ])
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
