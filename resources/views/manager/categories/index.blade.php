{{--
    Category list. Rendered server-side (so pagination and deep links work with
    no JavaScript), while delete and the visibility toggle post to the API.
--}}
@extends('manager.components.layout')

@section('title', 'Categories')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Product categories</h1>
            <p class="ph-sub">
                {{ $categories->total() }} {{ Str::plural('category', $categories->total()) }} &middot;
                how the storefront groups everything you sell
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.products.index') }}" class="btn-ghost">
                <i class="bi bi-box-seam"></i> Products
            </a>
            <a href="{{ route('manager.categories.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New category
            </a>
        </div>
    </div>

    {{-- POST keeps raw filters out of the address bar; the handler redirects to a sealed, bookmarkable URL. --}}
    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.categories.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.categories.index">
        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Name or slug…">
        </div>

        <div class="field">
            <label class="field-label" for="filterStatus">Status</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">All</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="hidden" @selected(($filters['status'] ?? '') === 'hidden')>Hidden</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterParent">Level</label>
            <select class="field-select" id="filterParent" name="parent_id">
                <option value="">All levels</option>
                <option value="root" @selected(($filters['parent_id'] ?? '') === 'root')>Top level only</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((string) ($filters['parent_id'] ?? '') === (string) $parent->id)>
                        Under {{ $parent->name }}
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
        <table class="data-table" data-list-table @if ($categories->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Parent</th>
                    <th class="num">Products</th>
                    <th class="num">Order</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr data-row>
                        <td class="cell-primary">
                            <div class="cell-media">
                                @if ($category->imageUrl())
                                    <img src="{{ $category->imageUrl() }}" alt="" class="cell-thumb" loading="lazy">
                                @else
                                    <span class="cell-thumb cell-thumb--empty"><i class="bi bi-image"></i></span>
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('manager.categories.edit', $category) }}" class="cell-title">
                                        {{ $category->name }}
                                    </a>
                                    <span class="cell-meta">/{{ $category->slug }}</span>
                                    @if ($category->is_featured)
                                        <span class="badge-status badge-warn ms-1">Featured</span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td data-label="Parent">
                            @if ($category->parent)
                                {{ $category->parent->name }}
                            @else
                                <span class="cell-meta">Top level</span>
                            @endif
                        </td>

                        <td class="num" data-label="Products">{{ $category->products_count }}</td>
                        <td class="num" data-label="Order">{{ $category->sort_order }}</td>

                        <td data-label="Status">
                            <span class="badge-status {{ $category->is_active ? 'badge-active' : 'badge-muted' }}"
                                  data-status-badge>
                                {{ $category->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>

                        <td class="actions" data-label="Actions">
                            <button type="button" class="row-btn"
                                    data-toggle-active="{{ route('api.manager.categories.toggle', $category) }}"
                                    title="{{ $category->is_active ? 'Hide from storefront' : 'Show on storefront' }}"
                                    aria-label="Toggle visibility">
                                <i class="bi {{ $category->is_active ? 'bi-eye' : 'bi-eye-slash' }}"></i>
                            </button>

                            <a href="{{ route('manager.categories.edit', $category) }}"
                               class="row-btn" title="Edit" aria-label="Edit category">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <button type="button" class="row-btn row-btn--danger"
                                    data-delete="{{ route('api.manager.categories.destroy', $category) }}"
                                    data-confirm-title="Delete this category?"
                                    data-confirm-message="{{ $category->name }} will be removed from the storefront navigation."
                                    title="Delete" aria-label="Delete category">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($categories->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-diagram-3"></i></div>
            <h3>No categories yet</h3>
            <p>Categories are how the storefront groups your products. Create the first one to get started.</p>
            <a href="{{ route('manager.categories.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New category
            </a>
        </div>

        @if ($categories->hasPages())
            <div class="table-foot">
                <span>
                    Showing {{ $categories->firstItem() }}&ndash;{{ $categories->lastItem() }}
                    of {{ $categories->total() }}
                </span>
                {{ $categories->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
