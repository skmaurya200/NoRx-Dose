{{--
    Journal posts. Server-rendered so filters and pagination are sealed links;
    the publish toggle and delete post to the API.
--}}
@extends('manager.components.layout')

@section('title', 'Journal')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Journal</h1>
            <p class="ph-sub">
                {{ $posts->total() }} {{ Str::plural('post', $posts->total()) }} &middot;
                everything on <code>/blogs</code> and the home page rail
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.blog.categories.index') }}" class="btn-ghost">
                <i class="bi bi-tags"></i> Categories
            </a>
            <a href="{{ route('manager.blog.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New post
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'All posts', 'value' => $summary['total'], 'icon' => 'bi-journal-text', 'tone' => 'icon-dark', 'query' => []],
            ['label' => 'Live', 'value' => $summary['published'], 'icon' => 'bi-broadcast', 'tone' => 'icon-gold', 'query' => ['status' => 'published']],
            ['label' => 'Drafts', 'value' => $summary['draft'], 'icon' => 'bi-pencil-square', 'tone' => 'icon-dark', 'query' => ['status' => 'draft']],
            ['label' => 'Reads', 'value' => $summary['views'], 'icon' => 'bi-eye', 'tone' => 'icon-gold', 'query' => []],
        ] as $card)
            <div class="col-6 col-xl-3">
                <a class="stat-card d-block" href="{{ \App\Support\ManagerQuery::url('manager.blog.index', $card['query']) }}">
                    <div class="stat-icon {{ $card['tone'] }}"><i class="bi {{ $card['icon'] }}"></i></div>
                    <div class="stat-label">{{ $card['label'] }}</div>
                    <div class="stat-value">{{ number_format($card['value']) }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <form class="filter-bar" method="POST" action="{{ route('manager.query') }}" data-filter-form
          data-filter-reset-url="{{ route('manager.blog.index') }}">
        @csrf
        <input type="hidden" name="target" value="manager.blog.index">
        <div class="field fb-search">
            <label class="field-label" for="filterSearch">Search</label>
            <input type="search" class="field-input" id="filterSearch" name="search"
                   value="{{ $filters['search'] ?? '' }}" placeholder="Title or body text…">
        </div>

        <div class="field">
            <label class="field-label" for="filterStatus">Status</label>
            <select class="field-select" id="filterStatus" name="status">
                <option value="">All</option>
                @foreach (\App\Models\BlogPost::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="filterCategory">Category</label>
            <select class="field-select" id="filterCategory" name="category_id">
                <option value="">All categories</option>
                <option value="none" @selected(($filters['category_id'] ?? '') === 'none')>Uncategorised</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>
                        {{ $category->name }}
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
        <table class="data-table" data-list-table @if ($posts->isEmpty()) hidden @endif>
            <thead>
                <tr>
                    <th>Post</th>
                    <th>Category</th>
                    <th>Published</th>
                    <th class="num">Read</th>
                    <th class="num">Views</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posts as $post)
                    <tr data-row>
                        <td class="cell-primary">
                            <div class="cell-media">
                                @if ($post->coverUrl())
                                    <img src="{{ $post->coverUrl() }}" alt="" class="cell-thumb" loading="lazy">
                                @else
                                    <span class="cell-thumb cell-thumb--empty"><i class="bi bi-image"></i></span>
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('manager.blog.edit', $post) }}" class="cell-title">
                                        {{ $post->title }}
                                    </a>
                                    <span class="cell-meta">/blogs/{{ $post->slug }}</span>
                                    @if ($post->is_featured)
                                        <span class="badge-status badge-warn ms-1">Featured</span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td data-label="Category">
                            @if ($post->category)
                                {{ $post->category->name }}
                            @else
                                <span class="cell-meta">Uncategorised</span>
                            @endif
                        </td>

                        <td data-label="Published">
                            @if ($post->published_at)
                                {{ $post->published_at->format('j M Y') }}
                                @if ($post->published_at->isFuture())
                                    <span class="cell-meta">scheduled</span>
                                @endif
                            @else
                                <span class="cell-meta">&mdash;</span>
                            @endif
                        </td>

                        <td class="num" data-label="Read">{{ $post->read_minutes }} min</td>
                        <td class="num" data-label="Views">{{ number_format($post->views_count) }}</td>

                        <td data-label="Status">
                            <span class="badge-status {{ $post->statusBadge() }}" data-status-badge>
                                {{ $post->statusLabel() }}
                            </span>
                        </td>

                        <td class="actions" data-label="Actions">
                            <button type="button" class="row-btn"
                                    data-toggle-active="{{ route('api.manager.blog.posts.toggle', $post) }}"
                                    data-on-label="Published" data-off-label="Draft"
                                    data-on-icon="bi-broadcast" data-off-icon="bi-file-earmark"
                                    data-on-title="Move back to draft" data-off-title="Publish this post"
                                    title="{{ $post->status === 'published' ? 'Move back to draft' : 'Publish this post' }}"
                                    aria-label="Toggle published">
                                <i class="bi {{ $post->status === 'published' ? 'bi-broadcast' : 'bi-file-earmark' }}"></i>
                            </button>

                            @if ($post->isPublished())
                                <a href="{{ route('blog-details', $post->slug) }}" class="row-btn"
                                   target="_blank" rel="noopener" title="View on the storefront"
                                   aria-label="View post">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            @endif

                            <a href="{{ route('manager.blog.edit', $post) }}"
                               class="row-btn" title="Edit" aria-label="Edit post">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <button type="button" class="row-btn row-btn--danger"
                                    data-delete="{{ route('api.manager.blog.posts.destroy', $post) }}"
                                    data-confirm-title="Delete this post?"
                                    data-confirm-message="{{ $post->title }} will disappear from the journal."
                                    title="Delete" aria-label="Delete post">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="empty-state" data-list-empty @if ($posts->isNotEmpty()) hidden @endif>
            <div class="es-icon"><i class="bi bi-journal-text"></i></div>
            <h3>Nothing written yet</h3>
            <p>The journal appears on the home page and at /blogs. Write the first post to fill it.</p>
            <a href="{{ route('manager.blog.create') }}" class="btn-gold">
                <i class="bi bi-plus-lg"></i> New post
            </a>
        </div>

        @if ($posts->hasPages())
            <div class="table-foot">
                <span>Showing {{ $posts->firstItem() }}&ndash;{{ $posts->lastItem() }} of {{ $posts->total() }}</span>
                {{ $posts->onEachSide(1)->links('manager.partials.pagination') }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
