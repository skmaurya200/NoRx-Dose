@extends('components.baselayout')

@section('title', 'All products - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/all-products.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="jar" viewBox="0 0 120 150">
        <rect x="44" y="6" width="32" height="16" rx="4" fill="#C9A227"/>
        <rect x="30" y="20" width="60" height="16" rx="5" fill="#B8912012"/>
        <rect x="26" y="30" width="68" height="106" rx="12" fill="#fff" stroke="#E7E2D6" stroke-width="2"/>
        <rect x="34" y="52" width="52" height="44" rx="6" fill="#F7EFD8"/>
        <rect x="42" y="64" width="36" height="4" rx="2" fill="#C9A227"/>
        <rect x="46" y="74" width="28" height="3" rx="1.5" fill="#D9CFAF"/>
        <rect x="50" y="82" width="20" height="3" rx="1.5" fill="#D9CFAF"/>
        <rect x="34" y="106" width="52" height="3" rx="1.5" fill="#EFEBE0"/>
        <rect x="34" y="114" width="38" height="3" rx="1.5" fill="#EFEBE0"/>
      </symbol>
      <symbol id="leaf" viewBox="0 0 200 200">
        <path d="M100 8c52 26 78 62 78 96 0 48-36 88-78 88S22 152 22 104C22 70 48 34 100 8zm0 30c-32 20-52 44-52 66 0 34 24 60 52 60s52-26 52-60c0-22-20-46-52-66z" fill="currentColor"/>
        <path d="M100 40v144" stroke="currentColor" stroke-width="7"/>
      </symbol>
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@section('content')
<!-- ==================== PAGE HEAD ==================== -->
<section class="head">
  <svg class="leaf leaf--a" viewBox="0 0 200 200" aria-hidden="true"><use href="#leaf"/></svg>
  <svg class="leaf leaf--b" viewBox="0 0 200 200" aria-hidden="true"><use href="#leaf"/></svg>

  <div class="shell head__in">
    <span class="head__count" id="catCount">{{ $products->count() }} {{ Str::plural('product', $products->count()) }}</span>
    <h1>{!! $content->html('hero.title') !!}</h1>
    <p>Everything we make, in one place. Batch tested, fragrance free, and shipped within 24 hours.</p>

    <div class="filters" id="filters">
      <button class="chip is-on" data-cat="all">All</button>
      @foreach ($categories as $category)
        <button class="chip" data-cat="{{ $category->name }}">{{ $category->name }}</button>
      @endforeach
    </div>
  </div>
</section>

<!-- ==================== CATALOG ==================== -->
<main class="catalog">
  <div class="shell">
    {{-- Rendered server-side; all-products.js only filters what is already here. --}}
    <div class="grid" id="grid">
      @foreach ($products as $product)
        @include('partials.product-card-catalog', ['product' => $product, 'index' => $loop->index])
      @endforeach

      {{-- Shown by the chip filter when nothing matches; also covers an empty catalogue. --}}
      <div class="empty" id="empty" @if ($products->isNotEmpty()) style="display:none" @endif>
        <h3>No products match that</h3>
        <p>Try a different search term, or clear the filter to see everything.</p>
      </div>
    </div>

    {{-- One block per category, under the grid, revealed by its own chip.
         Sections first, then the description underneath them - the sections
         are what somebody choosing between these products wants, and the
         description is the read for whoever is still there afterwards.

         Everything is written in the panel's editor and filtered by
         HtmlSanitizer on the way in, which is why the bodies are printed
         unescaped.

         All of them are rendered and hidden rather than fetched on click: the
         chips filter in the browser already, and a round trip to read a few
         hundred bytes is the slower answer. --}}
    @foreach ($categories as $category)
      @if (filled($category->accordions) || filled($category->description))
        <div class="catblock" data-cat-note="{{ $category->name }}" hidden>
          @if (filled($category->accordions))
            <section class="catacc" data-accordion>
              @foreach ($category->accordions as $section)
                <div class="catacc__item">
                  <button type="button" class="catacc__head" aria-expanded="false">
                    <span class="catacc__ic" aria-hidden="true">{{ ($section['icon'] ?? '') ?: '◆' }}</span>
                    <span class="catacc__title">{{ $section['label'] }}</span>
                    <span class="catacc__chev" aria-hidden="true">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                           stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m6 9 6 6 6-6"/>
                      </svg>
                    </span>
                  </button>
                  <div class="catacc__body" hidden>
                    <div class="catnote__rich">{!! $section['body'] !!}</div>
                  </div>
                </div>
                </div>
              @endforeach
            </section>
          @endif

          @if (filled($category->description))
            <section class="catnote">
              <h2 class="catnote__title">About {{ $category->name }}</h2>
              <div class="catnote__rich">{!! $category->description !!}</div>
            </section>
          @endif
        </div>
      @endif
    @endforeach
  </div>
</main>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/all-products.js') }}"></script>
@endpush
