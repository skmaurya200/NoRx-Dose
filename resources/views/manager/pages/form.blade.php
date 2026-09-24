{{--
    Edit one storefront page.

    The tabs, the panels and every input are generated from
    App\Support\Content\PageSchema - one tab per section - so adding a field is
    an entry there and a call in the storefront template, never a change here.
    Each field type has its own partial under pages/fields.

    Every tab is in the DOM at once and they post together, so which one
    happens to be open when Save is pressed makes no difference.

    Nothing here can alter the layout. A field left empty falls back to the copy
    the page was designed with, which is why the original text is a placeholder
    rather than a value: it can be seen, and clearing a box restores it.
--}}
@extends('manager.components.layout')

@section('title', $page['name'].' page')

@section('content')

    <div class="page-head page-head--tight">
        <div>
            <h1 class="font-serif">{{ $page['name'] }} page</h1>
            <p class="ph-sub">
                {{ count($page['sections']) }} {{ Str::plural('section', count($page['sections'])) }} &middot;
                words and pictures only, the design stays as it is.
                Clear a field to put its original wording back.
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ $liveUrl }}" class="btn-ghost" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right"></i> View page
            </a>
            <a href="{{ route('manager.pages.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> All pages
            </a>
        </div>
    </div>

    <form
        data-api-form="{{ route('api.manager.pages.update', $pageKey) }}"
        data-method="POST"
        data-success="{{ $page['name'] }} page updated."
        novalidate
    >
        @csrf

        <div class="form-tabs" role="tablist" aria-label="{{ $page['name'] }} sections">
            @foreach ($page['sections'] as $key => $section)
                <button type="button" role="tab"
                        @class(['form-tab', 'active' => $loop->first])
                        data-tab-target="tab-{{ $key }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="tab-{{ $key }}">
                    <i class="bi {{ $section['icon'] ?? 'bi-square' }}"></i> {{ $section['name'] }}
                    <span class="tab-dot" aria-hidden="true"></span>
                </button>
            @endforeach
        </div>

        @foreach ($page['sections'] as $key => $section)
            <div @class(['tab-pane-custom', 'active' => $loop->first]) id="tab-{{ $key }}" role="tabpanel">
                @include('manager.pages.section', ['sectionKey' => $key, 'section' => $section])
            </div>
        @endforeach
    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
