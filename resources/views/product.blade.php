@extends('components.baselayout')

@section('title', ($product->meta_title ?: $product->name).' - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/product.css') }}">
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
      <symbol id="i-bag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M6 6L5 2H2"/></symbol>
      <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></symbol>
      <symbol id="i-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15l6-6 6 6"/></symbol>
      <symbol id="i-flask" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7L4.5 19A2 2 0 006.2 22h11.6a2 2 0 001.7-3L14 9V2"/><path d="M9 2h6M7.5 15h9"/></symbol>
      <symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></symbol>
      <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></symbol>
      <symbol id="i-recycle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 19H4l3-5M17 19h3l-2-3.5M12 3l3 5M7 19l-2-3.5 4.5-8L12 3M17 19l2-3.5-4-7"/></symbol>
      <symbol id="i-box" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M3 7l9 5 9-5M12 12v10"/></symbol>
      <symbol id="i-star" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 .9-5 4.8 1.2 7-6.2-3.4L5.8 21 7 14.2 2 9.4l7-.9z"/></symbol>
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@section('content')
@php
    $detail = $product->detail;
    $categoryUrl = route('all-products').'#'.($product->category?->slug ?? '');

    // The size chooser. Never the weight or the shipping unit - those are
    // logistics, not something a customer picks between.
    $packs = $product->packs->map(fn ($pack) => [
        'label' => $pack->label,
        'price' => (float) $pack->price,
        'was' => $pack->compare_at_price !== null ? (float) $pack->compare_at_price : null,
        'best' => (bool) $pack->is_best_value,
        'stock' => $pack->stock_quantity,
    ])->values()->all();

    // A product with no sizes defined is sold as a single item, so the chooser
    // is not rendered at all rather than showing one meaningless option.
    $hasPacks = $packs !== [];

    // The three copy columns, rendered only where there is something to say.
    // The full description is no longer a fallback here - it has its own
    // section below, and using it twice meant the longer of the two was either
    // duplicated or never seen at all.
    $columns = collect([
        'What it is' => $product->short_description,
        'Benefits' => $detail?->benefits,
        'How to use' => $detail?->how_to_use,
    ])->filter();

    // Rich text from the panel's editor, filtered by HtmlSanitizer on the way
    // in - which is why it is printed unescaped further down.
    $fullDescription = trim((string) $product->description);

    /*
     | The accordions: the fixed three, then whatever this product's operator
     | added for it, in the order they were written.
     |
     | Each row carries whether its body is markup. The fixed three are plain
     | text typed into a textarea and must never render as HTML; the operator's
     | own sections are written in the panel's editor and were filtered by
     | HtmlSanitizer on the way in, so those are printed as markup.
     */
    $steps = collect([
        'Full ingredient list' => $detail?->ingredients,
        'Storage and shelf life' => $detail?->storage,
        'Safety and warnings' => $detail?->warnings,
    ])->filter()->map(fn ($body, $heading) => ['body' => $body, 'html' => false]);

    foreach ($detail?->extra_sections ?? [] as $section) {
        $heading = trim((string) ($section['label'] ?? ''));
        $body = trim((string) ($section['body'] ?? ''));

        if ($heading !== '' && $body !== '') {
            $steps->put($heading, ['body' => $body, 'html' => true]);
        }
    }

    $specifications = $detail?->specifications ?? [];

    // Short factual chips. Every one is a real column - nothing is asserted
    // about the product that the catalogue does not actually record.
    $facts = collect([
        $detail?->is_vegetarian ? 'Vegetarian' : null,
        $detail?->is_gluten_free ? 'Gluten free' : null,
        $product->unit ? $product->unit.' per pack' : null,
        $detail?->country_of_origin ? 'Made in '.$detail->country_of_origin : null,
        $detail?->manufacturer ? 'Made by '.$detail->manufacturer : null,
        $detail?->shelf_life_months ? $detail->shelf_life_months.' month shelf life' : null,
    ])->filter()->values();

    // The best picture this product has, else the default bottle - so the
    // gallery is one <img> rather than a branch.
    $gallery = $product->thumbnailUrl()
        ?? $product->images->firstWhere('is_primary', true)?->url()
        ?? $product->images->first()?->url()
        ?? \App\Support\DefaultImage::product();
@endphp

<!-- ==================== BREADCRUMB ==================== -->
<div class="shell">
  <nav class="crumb" aria-label="Breadcrumb">
    <a href="{{ route('home') }}">Home</a><i>›</i>
    <a href="{{ $categoryUrl }}">{{ $product->category?->name }}</a><i>›</i>
    <b>{{ $product->name }}</b>
  </nav>
</div>

<!-- ==================== PRODUCT TOP ==================== -->
<div class="shell">
  <section class="top" id="top">
    <div class="gallery">
      <div class="gallery__tags">
        <span class="gtag">✓ Batch tested</span>
        @if ($product->is_featured)
          <span class="gtag gtag--dark">Best seller</span>
        @endif
      </div>
      <img class="gallery__jar" src="{{ $gallery }}" alt="{{ $product->name }}">
    </div>

    <div class="buy">
      <p class="buy__eyebrow">{{ $product->category?->name }}</p>
      <h1>{{ $product->name }}</h1>

      <div class="rate">
        <span class="st">{{ $product->ratingStars() }}</span>
        <span>{{ number_format((float) $product->rating_avg, 1) }}</span>
        <a href="#reviewsPanel">({{ $product->rating_count }} {{ Str::plural('review', $product->rating_count) }})</a>
      </div>

      <div class="price">
        <span class="price__now" id="pNow">{{ $product->priceFormatted() }}</span>
        <span class="price__was" id="pWas">{{ $product->compareAtFormatted() }}</span>
        <span class="price__save" id="pSave">@if ($product->discountPercent())Save {{ $product->discountPercent() }}%@endif</span>
      </div>
      <p class="price__note">Free shipping over $50 · Ships within 24 hours</p>

      @if ($hasPacks)
        <div class="packHead">
          <span>Select size</span>
          <b id="packLabel"></b>
        </div>
      @endif

      {{-- product.js reads these and paints the selector, the price and the
           stock line from them. Present even with no packs, because the stock
           line and the cart still need the product's own figures. --}}
      <div class="packs" id="packs" role="radiogroup" aria-label="Select size"
           @if (! $hasPacks) hidden @endif
           data-packs="{{ json_encode($packs) }}"
           data-fallback-price="{{ (float) $product->price }}"
           data-fallback-stock="{{ $product->track_inventory ? $product->stock_quantity : '' }}"
           data-currency="{{ $product->currencySymbol() }}"
           data-unit-label="{{ config('shop.pack_unit_label') }}"
           data-product-id="{{ $product->id }}"
           data-product-name="{{ $product->name }}"
           data-product-badge="{{ $product->is_featured ? 'Best seller' : 'Batch tested' }}"
           data-product-image="{{ $gallery }}"
           data-low-stock="{{ $product->low_stock_threshold }}"
           data-allow-backorder="{{ $product->allow_backorder ? '1' : '' }}"></div>

      <div class="strip">
        <span class="strip__l"><i></i><span id="stockMsg"></span></span>
        <span class="clock" id="clock">
          <b data-u="h">00</b><i>:</i><b data-u="m">00</b><i>:</i><b data-u="s">00</b>
        </span>
      </div>

      <div class="actions">
        <div class="qty">
          <button type="button" id="minus" aria-label="Decrease quantity">−</button>
          <input type="number" id="qty" value="1" min="1" max="99" aria-label="Quantity">
          <button type="button" id="plus" aria-label="Increase quantity">+</button>
        </div>
        <button type="button" class="btn-cart" id="addCart"><svg><use href="#i-bag"/></svg>Add to cart</button>
        <button type="button" class="btn-buy" id="buyNow">Buy now</button>
      </div>

      <p class="buy__cat">Category: <a href="{{ $categoryUrl }}">{{ $product->category?->name }}</a></p>
    </div>
  </section>

  <!-- ==================== TRUST ==================== -->
  <section class="trust">
    <div class="tcard"><span class="tcard__ic"><svg><use href="#i-flask"/></svg></span><span><b>Batch tested</b><span>Report on every product page</span></span></div>
    <div class="tcard"><span class="tcard__ic"><svg><use href="#i-bolt"/></svg></span><span><b>Same-day dispatch</b><span>Order before 2pm</span></span></div>
    <div class="tcard"><span class="tcard__ic"><svg><use href="#i-lock"/></svg></span><span><b>Secure checkout</b><span>Card details never stored</span></span></div>
    <div class="tcard"><span class="tcard__ic"><svg><use href="#i-recycle"/></svg></span><span><b>Plastic-free packing</b><span>Kerbside recyclable</span></span></div>
  </section>

  <!-- ==================== DESCRIPTION ==================== -->
  <section class="panel is-open">
    <button class="panel__head" aria-expanded="true">
      <span class="panel__ic"><svg><use href="#i-box"/></svg></span>
      <h2>Description</h2>
      <span class="panel__chev"><svg><use href="#i-chev"/></svg></span>
    </button>

    <div class="panel__body"><div class="panel__inner"><div class="panel__pad">
      @if ($columns->isNotEmpty())
        <div class="cols">
          @foreach ($columns as $heading => $body)
            <div class="col">
              <h3>{{ $heading }}</h3>
              {{-- nl2br over an escaped string: this copy is plain text typed
                   in the panel, so it must never render as markup. --}}
              <p>{!! nl2br(e($body)) !!}</p>
            </div>
          @endforeach
        </div>
      @endif

      @foreach ($steps as $heading => $step)
        <div class="step @if ($loop->first) is-open @endif">
          <button class="step__btn" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
            <span class="step__n">{{ $loop->iteration }}</span>
            <span class="step__t">{{ $heading }}</span>
            <span class="step__sign"></span>
          </button>
          <div class="step__body"><div class="step__inner"><div class="step__pad">
            @if ($step['html'])
              <div class="rich">{!! $step['body'] !!}</div>
            @else
              {{-- nl2br over an escaped string: plain text typed in the panel,
                   so it must never render as markup. --}}
              <p>{!! nl2br(e($step['body'])) !!}</p>
            @endif
            @if ($heading === 'Storage and shelf life' && $detail?->shelf_life_months)
              <p>Best used within {{ $detail->shelf_life_months }} months of manufacture.</p>
            @endif
          </div></div></div>
        </div>
      @endforeach

      @if (! empty($specifications))
        <div class="step">
          <button class="step__btn" aria-expanded="false">
            <span class="step__n">{{ $steps->count() + 1 }}</span>
            <span class="step__t">Specifications</span>
            <span class="step__sign"></span>
          </button>
          <div class="step__body"><div class="step__inner"><div class="step__pad">
            <ul>
              @foreach ($specifications as $spec)
                <li><em>{{ $spec['label'] ?? '' }}</em> — {{ $spec['value'] ?? '' }}</li>
              @endforeach
            </ul>
          </div></div></div>
        </div>
      @endif

      @if ($facts->isNotEmpty())
        <div class="checks">
          @foreach ($facts as $fact)
            <div class="check"><i>✓</i>{{ $fact }}</div>
          @endforeach
        </div>
      @endif

      @if ($fullDescription !== '')
        {{-- Last in the panel: the columns and the accordions above are what a
             customer skims, and this is the long read for whoever wants it.

             Unescaped on purpose - App\Support\HtmlSanitizer cleaned this when
             it was saved, so the column only ever holds markup this page is
             willing to print. --}}
        <div class="rich">{!! $fullDescription !!}</div>
      @endif
    </div></div></div>
  </section>

  <!-- ==================== REVIEWS ==================== -->
  <section class="panel" id="reviewsPanel">
    <button class="panel__head" aria-expanded="false">
      <span class="panel__ic"><svg><use href="#i-star"/></svg></span>
      <h2>Reviews</h2>
      @if ($product->rating_count > 0)
        <span class="panel__count">{{ $product->rating_count }}</span>
      @endif
      <span class="panel__chev"><svg><use href="#i-chev"/></svg></span>
    </button>

    <div class="panel__body"><div class="panel__inner"><div class="panel__pad">
      {{--
          Approved reviews only. Nothing a customer submits below appears here
          until somebody in the panel has read it - including for the person
          who wrote it.
      --}}
      @if ($reviews->isNotEmpty())
        {{-- The average on the left, how it was arrived at on the right. The
             counts come from ReviewService::summary(), which has always
             returned them - this page simply never drew them. --}}
        <div class="prSum">
          <div class="prSum__score">
            <b>{{ number_format($reviewSummary['average'], 1) }}</b>
            <span class="st">{{ $product->ratingStars() }}</span>
            <span class="prSum__count">
              {{ $reviewSummary['total'] }} {{ Str::plural('review', $reviewSummary['total']) }}
            </span>
          </div>

          <ul class="prBars">
            @foreach ($reviewSummary['bars'] as $bar)
              <li class="prBars__row">
                <span class="prBars__label">{{ $bar['rating'] }}<i aria-hidden="true">★</i></span>
                <span class="prBars__track"
                      role="img"
                      aria-label="{{ $bar['rating'] }} stars: {{ $bar['count'] }} {{ Str::plural('review', $bar['count']) }} ({{ $bar['percent'] }}%)">
                  <span class="prBars__fill" style="width:{{ $bar['percent'] }}%"></span>
                </span>
                <span class="prBars__count">{{ $bar['count'] }}</span>
              </li>
            @endforeach
          </ul>
        </div>

        <ul class="prList">
          @foreach ($reviews as $review)
            <li class="prItem">
              <div class="prItem__top">
                <span class="prItem__av">{{ $review->initials() }}</span>
                <span class="prItem__who">
                  <b>{{ $review->author_name }}</b>
                  <span>{{ $review->byline() }} &middot; {{ $review->created_at?->format('j M Y') }}</span>
                </span>
                <span class="st">{{ $review->stars() }}</span>
              </div>
              @if ($review->title)
                <p class="prItem__title">{{ $review->title }}</p>
              @endif
              <p class="prItem__text">{{ $review->body }}</p>
            </li>
          @endforeach
        </ul>
      @else
        <p>No reviews for {{ $product->name }} yet. Be the first to write one.</p>
      @endif

      <form class="prForm" id="revForm" data-product="{{ $product->id }}" novalidate>
        <h3>Write a review</h3>
        <p class="prForm__note">
          We read every review before it goes up, so give us a day or two. Your
          email address is never shown &mdash; it is only how we match your review
          to your order.
        </p>

        {{-- Radios rather than a <select>: picking a rating is the one thing
             this form is for, and a dropdown hides it behind a click. Drawn
             5-to-1 so the CSS sibling selector can light up everything below
             the one being hovered; row-reverse puts them back in reading
             order on screen. --}}
        <div class="prForm__row">
          <span class="prForm__label" id="rratingLabel">Your rating</span>
          <div class="rstars" role="radiogroup" aria-labelledby="rratingLabel">
            @foreach ([5, 4, 3, 2, 1] as $star)
              <input type="radio" id="rstar{{ $star }}" name="rrating" value="{{ $star }}"
                     @checked($star === 5)>
              <label for="rstar{{ $star }}"
                     title="{{ $star }} out of 5">
                <span aria-hidden="true">★</span>
                <span class="visually-hidden">{{ $star }} {{ Str::plural('star', $star) }}</span>
              </label>
            @endforeach
          </div>
        </div>

        {{-- Two short fields that belong together, so they share a row until
             the screen is too narrow to hold both. --}}
        <div class="prForm__pair">
          <div class="prForm__row">
            <label for="rname">Your name</label>
            <input id="rname" type="text" placeholder="How it appears on the review" maxlength="120">
          </div>

          <div class="prForm__row">
            <label for="remail">Email</label>
            <input id="remail" type="email" placeholder="Not shown publicly" maxlength="180">
          </div>
        </div>

        <div class="prForm__row">
          <label for="rtext">Your review</label>
          <textarea id="rtext" rows="5" placeholder="How did it go?" maxlength="5000"></textarea>
        </div>

        <button type="submit" class="btn-cart" id="rSubmit">Submit review</button>
        <p class="prForm__out" id="formOk" role="status"></p>
      </form>
    </div></div></div>
  </section>
</div>

<!-- ==================== RELATED ==================== -->
@if ($related->isNotEmpty())
<section class="related">
  <div class="shell">
    <div class="related__head">
      <div>
        <p class="eyebrow">You may also like</p>
        <h2>Related products</h2>
      </div>
      <a href="{{ route('all-products') }}" class="related__all">View all</a>
    </div>

    <div class="rgrid">
      @foreach ($related as $item)
        <article class="rcard">
          <div class="rcard__media">
            <img src="{{ $item->thumbnailUrl() ?? \App\Support\DefaultImage::product() }}"
                 alt="{{ $item->name }}" loading="lazy">
          </div>
          <div class="rcard__body">
            <p class="rcard__eyebrow">{{ $item->category?->name }}</p>
            <h3>{{ $item->name }}</h3>
            <div class="st">{{ $item->ratingStars() }}</div>
            <div class="rcard__price">{{ $item->priceFormatted() }}</div>
            <a class="rcard__btn" href="{{ route('product', $item->slug) }}">View product</a>
          </div>
        </article>
      @endforeach
    </div>
  </div>
</section>
@endif

<!-- ==================== STICKY BUY BAR ==================== -->
<div class="sticky" id="sticky">
  <div class="shell sticky__in">
    <span class="sticky__name">{{ $product->name }}</span>
    <span class="sticky__price" id="sPrice">{{ $product->priceFormatted() }}</span>
    <button type="button" class="sticky__cart" id="sCart"><svg><use href="#i-bag"/></svg>Add to cart</button>
    <button type="button" class="sticky__buy" id="sBuy">Buy now</button>
  </div>
</div>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/product.js') }}"></script>
@endpush
