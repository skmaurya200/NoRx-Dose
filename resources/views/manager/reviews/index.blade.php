{{--
    The moderation queue. Nothing a customer writes reaches the storefront
    until it is approved here, so pending reviews are listed first whatever
    the filter.

    Server-rendered: filters and pagination are sealed links, and approve,
    reject, delete and restore post to the API.
--}}
@extends('manager.components.layout')

@section('title', 'Reviews')

@php
    use App\Support\ManagerQuery;

    $isTrash = ($filters['trashed'] ?? '') === 'only';
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Reviews</h1>
            <p class="ph-sub">
                {{ $reviews->total() }} {{ Str::plural('review', $reviews->total()) }} &middot;
                a review is invisible on the storefront until you approve it
            </p>
        </div>
        <div class="ph-actions">
            @if ($isTrash)
                <a href="{{ route('manager.reviews.index') }}" class="btn-ghost">
                    <i class="bi bi-arrow-left"></i> Back to reviews
                </a>
            @else
                <a href="{{ ManagerQuery::url('manager.reviews.index', ['trashed' => 'only']) }}" class="btn-ghost">
                    <i class="bi bi-trash"></i> Deleted
                </a>
                <a href="{{ route('manager.reviews.create') }}" class="btn-gold">
                    <i class="bi bi-plus-lg"></i> Write a review
                </a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'All reviews', 'value' => $summary['total'], 'icon' => 'bi-chat-quote', 'tone' => 'icon-dark', 'query' => []],
            ['label' => 'Waiting on you', 'value' => $summary['pending'], 'icon' => 'bi-hourglass-split', 'tone' => 'icon-gold', 'query' => ['status' => 'pending']],
            ['label' => 'Live', 'value' => $summary['approved'], 'icon' => 'bi-check-circle', 'tone' => 'icon-dark', 'query' => ['status' => 'approved']],
            ['label' => 'Rejected', 'value' => $summary['rejected'], 'icon' => 'bi-x-circle', 'tone' => 'icon-gold', 'query' => ['status' => 'rejected']],
        ] as $card)
            <div class="col-6 col-xl-3">
                <a class="stat-card d-block" href="{{ ManagerQuery::url('manager.reviews.index', $card['query']) }}">
                    <div class="stat-icon {{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-label">{{ $card['label'] }}</div>
                    <div class="stat-value">{{ number_format($card['value']) }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.reviews.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.reviews.index">
        @if ($isTrash)
            <input type="hidden" name="trashed" value="only">
        @endif

        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Name, title or wording…">
        </div>

        <div class="field">
            <label class="field-label" for="filterStatus">Status</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">All</option>
                @foreach (\App\Models\Review::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterRating">Rating</label>
            <select class="field-select" id="filterRating" name="rating">
                <option value="">Any</option>
                @foreach ([5, 4, 3, 2, 1] as $rating)
                    <option value="{{ $rating }}" @selected((string) ($filters['rating'] ?? '') === (string) $rating)>
                        {{ $rating }} {{ Str::plural('star', $rating) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterProduct">Product</label>
            <select class="field-select" id="filterProduct" name="product_id">
                <option value="">All</option>
                <option value="general" @selected(($filters['product_id'] ?? '') === 'general')>About the shop</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterSource">Written by</label>
            <select class="field-select" id="filterSource" name="source">
                <option value="">Anyone</option>
                <option value="customer" @selected(($filters['source'] ?? '') === 'customer')>Customer</option>
                <option value="manager" @selected(($filters['source'] ?? '') === 'manager')>Staff</option>
            </select>
        </div>

        <div class="fb-actions">
            <button type="submit" class="btn-ghost"><i class="bi bi-funnel"></i> Filter</button>
            <button type="button" class="btn-ghost" data-filter-reset>Clear</button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" data-list-table @if ($reviews->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>Review</th>
                    <th>About</th>
                    <th>Rating</th>
                    <th>Written by</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reviews as $review)
                    <tr data-row>
                        <td class="cell-primary">
                            <div class="min-w-0">
                                <a href="{{ route('manager.reviews.edit', $review->id) }}" class="cell-title">
                                    {{ $review->title ?: Str::limit($review->body, 60) }}
                                </a>
                                <span class="cell-meta">
                                    {{ $review->author_name }}@if ($review->location) &middot; {{ $review->location }}@endif
                                    &middot; {{ $review->created_at?->format('j M Y') }}
                                </span>
                            </div>
                        </td>

                        <td data-label="About">
                            @if ($review->product)
                                <a href="{{ route('manager.products.show', $review->product->id) }}">
                                    {{ $review->product->name }}
                                </a>
                            @else
                                <span>The shop</span>
                            @endif
                            <span class="cell-meta">{{ $review->categoryLabel() }}</span>
                        </td>

                        <td data-label="Rating">
                            <span title="{{ $review->rating }} out of 5">{{ $review->stars() }}</span>
                        </td>

                        <td data-label="Written by">
                            {{ $review->source === 'manager' ? 'Staff' : 'Customer' }}
                            @if ($review->is_verified)
                                <span class="cell-meta">Verified purchase</span>
                            @endif
                        </td>

                        <td data-label="Status">
                            <span class="badge-status {{ $review->statusBadge() }}" data-status-badge>
                                {{ ucfirst($review->status) }}
                            </span>
                            @if ($review->is_featured)
                                <span class="badge-status badge-draft ms-1"
                                      title="Pulled out at the top of the reviews page">Featured</span>
                            @endif
                        </td>

                        <td class="actions" data-label="Actions">
                            @if ($isTrash)
                                <button type="button" class="row-btn"
                                        data-restore="{{ route('api.manager.reviews.restore', $review->id) }}"
                                        title="Restore" aria-label="Restore review">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            @else
                                {{-- The shared toggle handler needs this module's
                                     wording, or it would say "Hidden" at a review. --}}
                                <button type="button" class="row-btn"
                                        data-toggle-active="{{ route('api.manager.reviews.toggle', $review->id) }}"
                                        data-on-label="Approved" data-off-label="Rejected"
                                        data-on-icon="bi-check-circle" data-off-icon="bi-x-circle"
                                        data-on-title="Take this off the storefront"
                                        data-off-title="Publish this review"
                                        title="{{ $review->isApproved() ? 'Take this off the storefront' : 'Publish this review' }}"
                                        aria-label="Approve or reject review">
                                    <i class="bi {{ $review->isApproved() ? 'bi-check-circle' : 'bi-x-circle' }}"></i>
                                </button>

                                <a href="{{ route('manager.reviews.edit', $review->id) }}"
                                   class="row-btn" title="Edit" aria-label="Edit review">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <button type="button" class="row-btn row-btn--danger"
                                        data-delete="{{ route('api.manager.reviews.destroy', $review->id) }}"
                                        data-confirm-title="Delete this review?"
                                        data-confirm-message="It comes off the storefront and out of the product rating. You can restore it from Deleted."
                                        title="Delete" aria-label="Delete review">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($reviews->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-chat-quote"></i></div>
            <h3>{{ $isTrash ? 'Nothing deleted' : 'No reviews yet' }}</h3>
            <p>
                {{ $isTrash
                    ? 'Reviews you delete are kept here so a mis-click can be undone.'
                    : 'Reviews left on a product page land here for approval. You can also write one yourself.' }}
            </p>
            @unless ($isTrash)
                <a href="{{ route('manager.reviews.create') }}" class="btn-gold">
                    <i class="bi bi-plus-lg"></i> Write a review
                </a>
            @endunless
        </div>

        @if ($reviews->hasPages())
            <div class="table-foot">
                <span>Showing {{ $reviews->firstItem() }}&ndash;{{ $reviews->lastItem() }} of {{ $reviews->total() }}</span>
                {{ $reviews->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
