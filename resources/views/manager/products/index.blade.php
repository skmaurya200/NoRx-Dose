{{--
    Product list. Server-rendered so pagination, sorting and filtering all work
    as sealed links; the status picker and delete post to the API.
--}}
@extends('manager.components.layout')

@section('title', 'Products')

@php
    use App\Support\ManagerQuery;

    // Clicking the current sort column flips the direction, any other column
    // starts ascending. Query string is preserved so a sort keeps the filters.
    $currentSort = $filters['sort'] ?? 'created_at';
    $currentDirection = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

    $sortLink = function (string $column) use ($filters, $currentSort, $currentDirection) {
        return ManagerQuery::url('manager.products.index', array_merge($filters, [
            'sort' => $column,
            'direction' => $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc',
            'page' => null,
        ]));
    };

    $sortIcon = fn (string $column) => $currentSort !== $column
        ? 'bi-arrow-down-up opacity-25'
        : ($currentDirection === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down');
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Products</h1>
            <p class="ph-sub">Your catalogue, pricing and stock levels.</p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.categories.index') }}" class="btn-ghost">
                <i class="bi bi-diagram-3"></i> Categories
            </a>
            <a href="{{ route('manager.products.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New product
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'All products', 'value' => $summary['total'], 'icon' => 'bi-box-seam', 'tone' => 'icon-dark', 'query' => []],
            ['label' => 'Active', 'value' => $summary['active'], 'icon' => 'bi-check-circle', 'tone' => 'icon-gold', 'query' => ['status' => 'active']],
            ['label' => 'Drafts', 'value' => $summary['draft'], 'icon' => 'bi-pencil-square', 'tone' => 'icon-dark', 'query' => ['status' => 'draft']],
            ['label' => 'Low stock', 'value' => $summary['low_stock'], 'icon' => 'bi-exclamation-triangle', 'tone' => 'icon-gold', 'query' => ['stock' => 'low']],
        ] as $card)
            <div class="col-6 col-xl-3">
                {{-- Each card is a filter shortcut, not just a number --}}
                <a class="stat-card d-block"
                   href="{{ ManagerQuery::url('manager.products.index', $card['query']) }}">
                    <div class="stat-icon {{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-label">{{ $card['label'] }}</div>
                    <div class="stat-value">{{ number_format($card['value']) }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.products.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.products.index">
        {{-- Carried through so filtering does not silently reset the sort --}}
        <input type="hidden" name="sort" value="{{ $currentSort }}">
        <input type="hidden" name="direction" value="{{ $currentDirection }}">

        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Name, SKU or brand…">
        </div>

        <div class="field">
            <label class="field-label" for="filterCategory">Category</label>
            <select class="field-select" id="filterCategory" name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $option)
                    <option value="{{ $option['id'] }}"
                            @selected((string) ($filters['category_id'] ?? '') === (string) $option['id'])>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterStatus">Status</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">Any status</option>
                @foreach (\App\Models\Product::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterStock">Stock</label>
            <select class="field-select" id="filterStock" name="stock">
                <option value="">Any stock</option>
                <option value="in" @selected(($filters['stock'] ?? '') === 'in')>In stock</option>
                <option value="low" @selected(($filters['stock'] ?? '') === 'low')>Low stock</option>
                <option value="out" @selected(($filters['stock'] ?? '') === 'out')>Out of stock</option>
            </select>
        </div>

        <div class="fb-actions">
            <button type="submit" class="btn-ghost"><i class="bi bi-funnel"></i> Filter</button>
            <button type="button" class="btn-ghost" data-filter-reset>Clear</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" data-list-table @if ($products->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>
                        <a href="{{ $sortLink('name') }}">Product <i class="bi {{ $sortIcon('name') }}"></i></a>
                    </th>
                    <th>Category</th>
                    <th class="num">
                        <a href="{{ $sortLink('price') }}">Price <i class="bi {{ $sortIcon('price') }}"></i></a>
                    </th>
                    <th class="num">
                        <a href="{{ $sortLink('stock_quantity') }}">Stock <i class="bi {{ $sortIcon('stock_quantity') }}"></i></a>
                    </th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $product)
                    <tr data-row>
                        <td class="cell-primary">
                            <div class="cell-media">
                                @if ($product->thumbnailUrl())
                                    <img src="{{ $product->thumbnailUrl() }}" alt="" class="cell-thumb" loading="lazy">
                                @else
                                    <span class="cell-thumb cell-thumb--empty"><i class="bi bi-image"></i></span>
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('manager.products.show', $product) }}" class="cell-title">
                                        {{ $product->name }}
                                    </a>
                                    <span class="cell-meta">
                                        SKU {{ $product->sku }}
                                        @if ($product->is_featured)
                                            &middot; <span class="text-warning-emphasis">Featured</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </td>

                        <td data-label="Category">
                            {{ $product->category?->name ?? '—' }}
                        </td>

                        <td class="num" data-label="Price">
                            <div>{{ $product->currency }} {{ number_format((float) $product->price, 2) }}</div>
                            @if ($product->discountPercent())
                                <span class="cell-meta">{{ $product->discountPercent() }}% off</span>
                            @endif
                        </td>

                        <td class="num" data-label="Stock">
                            @if (! $product->track_inventory)
                                <span class="badge-status badge-muted">Not tracked</span>
                            @else
                                <div>{{ number_format($product->stock_quantity) }}</div>
                                @if ($product->isOutOfStock())
                                    <span class="badge-status badge-danger">Out of stock</span>
                                @elseif ($product->isLowStock())
                                    <span class="badge-status badge-warn">Low</span>
                                @endif
                            @endif
                        </td>

                        <td data-label="Status">
                            {{-- Changing this saves immediately; no separate submit --}}
                            <select class="field-select status-select status-{{ $product->status }}"
                                    data-status-select="{{ route('api.manager.products.status', $product) }}"
                                    aria-label="Status for {{ $product->name }}">
                                @foreach (\App\Models\Product::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($product->status === $status)>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td class="actions" data-label="Actions">
                            <a href="{{ route('manager.products.show', $product) }}"
                               class="row-btn" title="View details" aria-label="View details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('manager.products.edit', $product) }}"
                               class="row-btn" title="Edit" aria-label="Edit product">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="row-btn row-btn--danger"
                                    data-delete="{{ route('api.manager.products.destroy', $product) }}"
                                    data-confirm-title="Delete this product?"
                                    data-confirm-message="{{ $product->name }} will be removed from the storefront. Past orders keep their record of it."
                                    title="Delete" aria-label="Delete product">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($products->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-box-seam"></i></div>
            <h3>
                @if (array_filter($filters))
                    Nothing matches those filters
                @else
                    No products yet
                @endif
            </h3>
            <p>
                @if (array_filter($filters))
                    Try clearing the filters, or search for something else.
                @else
                    Add your first product and it will appear on the storefront as soon as you set it to active.
                @endif
            </p>
            @if (array_filter($filters))
                <a href="{{ route('manager.products.index') }}" class="btn-ghost">Clear filters</a>
            @else
                <a href="{{ route('manager.products.create') }}" class="btn-gold">
                    <i class="bi bi-plus-lg"></i> New product
                </a>
            @endif
        </div>

        @if ($products->hasPages())
            <div class="table-foot">
                <span>
                    Showing {{ $products->firstItem() }}&ndash;{{ $products->lastItem() }}
                    of {{ $products->total() }}
                </span>
                {{ $products->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
