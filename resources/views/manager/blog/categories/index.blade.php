{{--
    Journal categories - the chips above the public post list.
--}}
@extends('manager.components.layout')

@section('title', 'Journal categories')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Journal categories</h1>
            <p class="ph-sub">
                {{ $categories->total() }} {{ Str::plural('category', $categories->total()) }} &middot;
                the filter chips above the post list
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.blog.index') }}" class="btn-ghost">
                <i class="bi bi-journal-text"></i> Posts
            </a>
            <a href="{{ route('manager.blog.categories.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New category
            </a>
        </div>
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.blog.categories.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.blog.categories.index">
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
                    <th class="num">Posts</th>
                    <th class="num">Order</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr data-row>
                        <td class="cell-primary">
                            <div class="min-w-0">
                                <a href="{{ route('manager.blog.categories.edit', $category) }}" class="cell-title">
                                    {{ $category->name }}
                                </a>
                                <span class="cell-meta">
                                    {{ $category->description ?: '/blogs?category='.$category->slug }}
                                </span>
                            </div>
                        </td>

                        <td class="num" data-label="Posts">{{ $category->posts_count }}</td>
                        <td class="num" data-label="Order">{{ $category->sort_order }}</td>

                        <td data-label="Status">
                            <span class="badge-status {{ $category->is_active ? 'badge-active' : 'badge-muted' }}"
                                  data-status-badge>
                                {{ $category->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>

                        <td class="actions" data-label="Actions">
                            <button type="button" class="row-btn"
                                    data-toggle-active="{{ route('api.manager.blog.categories.toggle', $category) }}"
                                    title="{{ $category->is_active ? 'Hide from the journal' : 'Show on the journal' }}"
                                    aria-label="Toggle visibility">
                                <i class="bi {{ $category->is_active ? 'bi-eye' : 'bi-eye-slash' }}"></i>
                            </button>

                            <a href="{{ route('manager.blog.categories.edit', $category) }}"
                               class="row-btn" title="Edit" aria-label="Edit category">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <button type="button" class="row-btn row-btn--danger"
                                    data-delete="{{ route('api.manager.blog.categories.destroy', $category) }}"
                                    data-confirm-title="Delete this category?"
                                    data-confirm-message="{{ $category->name }} will be removed from the journal filter."
                                    title="Delete" aria-label="Delete category">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($categories->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-tags"></i></div>
            <h3>No categories yet</h3>
            <p>Categories are optional — a post files fine without one — but they give readers a way in.</p>
            <a href="{{ route('manager.blog.categories.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New category
            </a>
        </div>

        @if ($categories->hasPages())
            <div class="table-foot">
                <span>Showing {{ $categories->firstItem() }}&ndash;{{ $categories->lastItem() }} of {{ $categories->total() }}</span>
                {{ $categories->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
