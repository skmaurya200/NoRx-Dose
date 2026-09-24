@extends('components.baselayout')

@section('title', 'Shop - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/shop.css') }}">
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
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@section('content')
<!-- ==================== PROMO ==================== -->
@if ($promotion !== null)
  <section
    class="promo"
    id="promotion"
    @if ($promotion['ends_at_iso'] !== null) data-promotion-ends-at="{{ $promotion['ends_at_iso'] }}" @endif
  >
    <div class="shell promo__in">
      <span class="promo__spark">✦</span>
      <span>
        {{ $promotion['ends_at_iso'] !== null ? 'Offer ends in —' : 'Seasonal offer —' }}
        <strong>{{ $promotion['promotion_label'] }}</strong>. Code
      </span>
      <span class="promo__code">{{ $promotion['code'] }}</span>
      @if ($promotion['ends_at_iso'] !== null)
        <div class="clock" id="clock" aria-label="Time remaining for this offer">
          <span class="clock__cell"><b data-unit="h">00</b><span>HRS</span></span>
          <span class="clock__sep">:</span>
          <span class="clock__cell"><b data-unit="m">00</b><span>MIN</span></span>
          <span class="clock__sep">:</span>
          <span class="clock__cell"><b data-unit="s">00</b><span>SEC</span></span>
        </div>
      @endif
    </div>
  </section>
@endif

<!-- ==================== TOOLBAR ==================== -->
<div class="bar">
  <div class="shell bar__in">
    <p class="bar__count">Showing <b id="range">{{ $products->total() ? $products->firstItem().'–'.$products->lastItem() : 0 }}</b> of <b id="total">{{ $products->total() }}</b> results</p>
    <div class="bar__right">
      <label class="sel">
        {{-- Sorting is applied server-side, so it survives pagination. --}}
        <select id="sort" aria-label="Sort products">
          <option value="default" @selected($sort === 'default')>Default sorting</option>
          <option value="popular" @selected($sort === 'popular')>Sort by popularity</option>
          <option value="rating" @selected($sort === 'rating')>Sort by rating</option>
          <option value="new" @selected($sort === 'new')>Sort by latest</option>
          <option value="low" @selected($sort === 'low')>Price: low to high</option>
          <option value="high" @selected($sort === 'high')>Price: high to low</option>
        </select>
      </label>
      <div class="view">
        <button id="vGrid" class="is-on" aria-label="Grid view" aria-pressed="true">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        </button>
        <button id="vList" aria-label="List view" aria-pressed="false">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ==================== PRODUCTS ==================== -->
<main class="shop">
  <div class="shell shop__in">
    <div class="shop__main">
      @if ($category !== null)
        <div class="shop__head">
          <h1>{{ $category->name }}</h1>
          <a href="{{ route('shop') }}" class="shop__clear">× Clear filter</a>
        </div>
      @endif

      {{-- Rendered server-side; shop.js only handles the view toggle and wishlist. --}}
      <div class="grid" id="grid">
        @forelse ($products as $product)
          @include('partials.product-card-shop', ['product' => $product, 'index' => $loop->index])
        @empty
          <div class="empty">
            @if ($category !== null)
              <h3>Nothing in {{ $category->name }} yet</h3>
              <p>Products appear here as soon as they go live. <a href="{{ route('shop') }}">Browse everything</a>.</p>
            @else
              <h3>Nothing in the shop yet</h3>
              <p>New products appear here as soon as they go live.</p>
            @endif
          </div>
        @endforelse
      </div>

      {{ $products->onEachSide(1)->links('vendor.pagination.aurum') }}

      {{-- The category's own copy, written in the panel's editor and filtered
           by HtmlSanitizer on the way in - which is why it is printed
           unescaped. It sits under the products because a customer came here
           for the products first. --}}
      {{-- The category's own sections, then its description under them. Both
           written in the panel's editor and filtered by HtmlSanitizer on the
           way in, which is why the bodies are printed unescaped. --}}
      @if ($category !== null && filled($category->accordions))
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

      @if ($category !== null && filled($category->description))
        <section class="catnote">
          <h2 class="catnote__title">About {{ $category->name }}</h2>
          <div class="catnote__rich">{!! $category->description !!}</div>
        </section>
      @endif
    </div>

    {{-- Every live category, so a customer can move between them without
         going back to the home page. --}}
    <aside class="shop__side" aria-label="Shop by category">
      <h2 class="side__title">Categories</h2>
      <ul class="side__list">
        <li>
          <a href="{{ route('shop') }}" @class(['is-on' => $category === null])>All products</a>
        </li>
        @foreach ($categories as $sideCategory)
          <li>
            <a href="{{ route('shop', ['category' => $sideCategory->slug]) }}"
               @class(['is-on' => $category?->id === $sideCategory->id])
               @if ($category?->id === $sideCategory->id) aria-current="page" @endif>
              {{ $sideCategory->name }}
            </a>
          </li>
        @endforeach
      </ul>
    </aside>
  </div>
</main>

<!-- ==================== NEWSLETTER ==================== -->
<section class="news">
  <div class="shell">
    <div class="news__box">
      <div class="news__in">
        <h2>{!! $content->html('newsletter.title') !!}</h2>
        <p>{{ $content->text('newsletter.subtitle') }}</p>
        <form class="news__form" id="newsForm">
          <input type="email" placeholder="{{ $content->text('newsletter.placeholder') }}" aria-label="Email address" required>
          <button type="submit">{{ $content->text('newsletter.button') }}</button>
        </form>
        <p class="news__ok" id="newsOk" role="status"></p>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/shop.js') }}"></script>
@endpush
