{{--
    The storefront's fixed pages.

    Not a CRUD list: these pages are templates, so they cannot be created or
    deleted here. What can be changed is the copy and the imagery on them,
    which is what each row links to.
--}}
@extends('manager.components.layout')

@section('title', 'Pages')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">Pages</h1>
            <p class="ph-sub">
                The words and pictures on the storefront's fixed pages.
                The layout belongs to the design and does not change.
            </p>
        </div>
    </div>

    <div class="page-cards">
        @foreach ($pages as $page)
            {{-- A div, not an anchor: the card carries two destinations, and a
                 link inside a link is not valid markup. --}}
            <div class="page-card">
                <div class="page-card__head">
                    <a class="page-card__name" href="{{ route('manager.pages.edit', $page['key']) }}">
                        {{ $page['name'] }}
                    </a>
                    @if ($page['edited'] > 0)
                        <span class="badge-status badge-active">Edited</span>
                    @else
                        {{-- Not a warning. A page nobody has touched is showing
                             the copy it was designed with, which is fine. --}}
                        <span class="badge-status badge-muted">Original</span>
                    @endif
                </div>

                <p class="page-card__meta">
                    {{ $page['sections'] }} {{ Str::plural('tab', $page['sections']) }}
                    &middot; {{ $page['fields'] }} {{ Str::plural('field', $page['fields']) }}
                    @if ($page['edited'] > 0)
                        &middot; {{ $page['edited'] }} changed
                    @endif
                </p>

                <div class="page-card__foot">
                    <a class="page-card__edit" href="{{ route('manager.pages.edit', $page['key']) }}">
                        <i class="bi bi-pencil"></i> Edit content
                    </a>
                    <a class="page-card__view" href="{{ $page['url'] }}" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right"></i> View
                    </a>
                </div>
            </div>
        @endforeach
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
