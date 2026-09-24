{{--
    Shop pagination. Renders the exact markup the static mockup used:

        <nav class="pager"><span class="is-here">1</span><a>2</a>
             <span class="gap">…</span><a>14</a><a aria-label="Next page">›</a></nav>

    Laravel's default pagination views ship Tailwind or Bootstrap markup, which
    would drag in classes this stylesheet knows nothing about - hence a custom
    view rather than a published one.
--}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">

        {{-- Previous. Absent on page one, which is what the mockup showed. --}}
        @if ($paginator->onFirstPage())
            {{-- no previous link --}}
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>
        @endif

        @foreach ($elements as $element)
            {{-- A string element is the "…" separator between page ranges. --}}
            @if (is_string($element))
                <span class="gap">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="is-here" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>
        @endif
    </nav>
@endif
