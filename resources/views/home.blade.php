@extends('components.baselayout')

@section('title', 'NoRx Dose - Modern science, bespoke wellness')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/home.css') }}">
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
<!-- ==================== HERO ==================== -->
{{-- Copy and imagery come from the Pages module; the markup, the classes and
     the animation offsets are the design and are not editable. Every value
     falls back to the words this template was written with. --}}
<section class="hero" id="hero">
  <div class="hero__bg">
    <img src="{{ $content->image('hero.background', 'hero') }}" alt="" fetchpriority="high" decoding="async">
  </div>
  <div class="hero__scrim"></div>

  <div class="shell">
    <div class="hero__in">
      <span class="hero__tag" data-in style="--i:0"><i></i>{{ $content->text('hero.tag') }}</span>
      <h1 data-in style="--i:1">{!! $content->html('hero.title') !!}</h1>
      <p class="hero__sub" data-in style="--i:2">
        {{ $content->text('hero.subtitle') }}
      </p>
      <div class="hero__cta" data-in style="--i:3">
        <a href="{{ $content->text('hero.primary_link') }}" class="btn-a">{{ $content->text('hero.primary_label') }}</a>
        <a href="{{ $content->text('hero.secondary_link') }}" class="btn-b">{{ $content->text('hero.secondary_label') }}</a>
      </div>
    </div>
  </div>
  <span class="hero__scroll">{{ $content->text('hero.scroll_label') }}</span>
</section>

<!-- ==================== ABOUT ==================== -->
<section class="section about" id="about">
  <div class="shell about__grid">
    <div data-reveal>
      <p class="eyebrow">{{ $content->text('about.eyebrow') }}</p>
      <h2>{!! $content->html('about.title') !!}</h2>
      <p class="lead">
        {{ $content->text('about.lead') }}
      </p>
      <div class="mini">
        <div class="mini__card">
          <i></i>
          <h4>{{ $content->text('about.card_one_title') }}</h4>
          <p>{{ $content->text('about.card_one_text') }}</p>
        </div>
        <div class="mini__card">
          <i></i>
          <h4>{{ $content->text('about.card_two_title') }}</h4>
          <p>{{ $content->text('about.card_two_text') }}</p>
        </div>
      </div>
    </div>

    <div class="collage" data-reveal style="--d:120ms">
      <div class="c1">
        <img src="{{ $content->image('about.image_tall', 'portrait') }}" alt="" loading="lazy" decoding="async">
      </div>
      <div class="c2">
        <img src="{{ $content->image('about.image_wide', 'consult') }}" alt="" loading="lazy" decoding="async">
      </div>
      <div class="c3">
        <strong data-count="{{ (int) $content->text('about.badge_value') }}"
                data-suffix="{{ $content->text('about.badge_suffix') }}">0{{ $content->text('about.badge_suffix') }}</strong>
        <span>{{ $content->text('about.badge_label') }}</span>
      </div>
    </div>
  </div>
</section>

<!-- ==================== STATS ==================== -->
<section class="stats">
  {{-- The comma grouping is per slot and belongs to the design, so it is
       listed here; the figures, suffixes and captions are editable. --}}
  @php
      $stats = ['one' => true, 'two' => false, 'three' => false, 'four' => true];
  @endphp

  <div class="shell stats__grid">
    @foreach ($stats as $slot => $grouped)
      @php
          $value = (int) $content->text("stats.{$slot}_value");
          $suffix = $content->text("stats.{$slot}_suffix");
      @endphp

      <div class="stats__item">
        <div class="stats__n" data-count="{{ $value }}" data-suffix="{{ $suffix }}"
             @if ($grouped) data-comma @endif>0</div>
        <div class="stats__l">{{ $content->text("stats.{$slot}_label") }}</div>
      </div>
    @endforeach
  </div>
</section>

<!-- ==================== CATEGORIES ==================== -->
<section class="section cats" id="shop">
  <div class="shell">
    <p class="eyebrow eyebrow--center" data-reveal>{{ $content->text('cats.eyebrow') }}</p>
    <h2 data-reveal style="--d:60ms">{!! $content->html('cats.title') !!}</h2>
    @php
        /*
         | The row drifts on its own and can also be stepped through with the
         | arrows, so it is a real scroller rather than a CSS transform: a
         | transform cannot be scrolled, and the two would fight.
         |
         | The groups are repeated so the track is never narrower than the
         | viewport - which would leave a gap - and so the drift can wrap by
         | subtracting one group width the moment it passes one.
         */
        $catRepeat = $categories->isEmpty() ? 1 : max(2, (int) ceil(12 / $categories->count()));
    @endphp

    @if ($categories->isNotEmpty())
      <div class="catmq" data-reveal style="--d:120ms" data-catmq>
        <button type="button" class="catmq__btn catmq__btn--prev" data-catmq-dir="-1"
                aria-label="Previous categories">‹</button>

        <div class="catmq__view" data-catmq-view>
          <div class="catmq__track" data-catmq-track data-catmq-groups="{{ $catRepeat }}">
            @for ($pass = 0; $pass < $catRepeat; $pass++)
              {{-- Only the first group is read out; the rest are the same links
                   again and would be announced, and tabbed through, twice. --}}
              <div class="catmq__group" @if ($pass > 0) aria-hidden="true" @endif>
                @foreach ($categories as $category)
                  <a href="{{ route('shop', ['category' => $category->slug]) }}" class="cat"
                     @if ($pass > 0) tabindex="-1" @endif>
                    <span class="cat__ic">◇</span>
                    <h4>{{ $category->name }}</h4>
                  </a>
                @endforeach
              </div>
            @endfor
          </div>
        </div>

        <button type="button" class="catmq__btn catmq__btn--next" data-catmq-dir="1"
                aria-label="Next categories">›</button>
      </div>
    @endif
  </div>
</section>

<!-- ==================== FEATURED RAIL ==================== -->
<section class="section section--tight" id="featured">
  <div class="shell">
    <div class="rail__head" data-reveal>
      <div>
        <p class="eyebrow">{{ $content->text('favourites.eyebrow') }}</p>
        <h2>{!! $content->html('favourites.title') !!}</h2>
      </div>
      {{-- Where the carousel arrows used to be. A grid shows all ten at once,
           so there is nothing left to page through - what is worth offering is
           the way out to the rest of the catalogue. --}}
      <a href="{{ route('all-products') }}" class="prow__all">View all products</a>
    </div>

    <div class="prow" id="favs" data-reveal style="--d:80ms">
      @forelse ($featured as $product)
        @include('partials.product-card-rail', ['product' => $product])
      @empty
        {{-- No live products yet - the grid stays empty rather than showing placeholders. --}}
      @endforelse
    </div>

    {{-- The same link again, under the last card. On a phone the one in the
         heading is off the top of the screen by the time the grid has been
         read, which is exactly when it is wanted. --}}
    @if ($featured->isNotEmpty())
      <a href="{{ route('all-products') }}" class="prow__all prow__all--under">View all products</a>
    @endif
  </div>
</section>

<!-- ==================== BAND ==================== -->
<section class="section band">
  <div class="shell band__grid">
    <div class="band__media" data-reveal>
      <img src="{{ $content->image('band.image', 'lab') }}" alt="" loading="lazy" decoding="async">
      <span class="band__chip">{{ $content->text('band.chip') }}</span>
    </div>
    <div data-reveal style="--d:120ms">
      <p class="eyebrow">{{ $content->text('band.eyebrow') }}</p>
      <h2>{!! $content->html('band.title') !!}</h2>
      <p>
        {{ $content->text('band.text') }}
      </p>
      {{-- A line cleared in the panel drops out rather than leaving an empty
           bullet behind. --}}
      @php
          $bullets = collect(['item_one', 'item_two', 'item_three'])
              ->map(fn ($key) => $content->text("band.{$key}"))
              ->filter();
      @endphp

      <ul class="band__list">
        @foreach ($bullets as $bullet)
          <li>{{ $bullet }}</li>
        @endforeach
      </ul>
      <a href="{{ $content->text('band.button_link') }}" class="btn-a">{{ $content->text('band.button_label') }}</a>
    </div>
  </div>
</section>

<!-- ==================== NEW ARRIVALS ==================== -->
<section class="section section--tight" id="new">
  <div class="shell">
    <div class="rail__head" data-reveal>
      <div>
        <p class="eyebrow">{{ $content->text('arrivals.eyebrow') }}</p>
        <h2>{!! $content->html('arrivals.title') !!}</h2>
      </div>
      <a href="{{ route('all-products') }}" class="prow__all">View all products</a>
    </div>

    <div class="prow" id="fresh" data-reveal style="--d:80ms">
      @forelse ($arrivals as $product)
        @include('partials.product-card-rail', ['product' => $product])
      @empty
        {{-- Nothing new beyond what the favourites grid already shows. --}}
      @endforelse
    </div>

    @if ($arrivals->isNotEmpty())
      <a href="{{ route('all-products') }}" class="prow__all prow__all--under">View all products</a>
    @endif
  </div>
</section>

<!-- ==================== JOURNAL ==================== -->
<section class="section journal" id="journal">
  <div class="shell">
    <div class="journal__head" data-reveal>
      <div>
        <p class="eyebrow">{{ $content->text('journal.eyebrow') }}</p>
        <h2>{!! $content->html('journal.title') !!}</h2>
      </div>
      <a href="{{ route('blogs') }}" class="journal__link">{{ $content->text('journal.link_label') }}</a>
    </div>

    @if ($posts->isEmpty())
      <p class="journal__none" data-reveal>{{ $content->text('journal.empty_text') }}</p>
    @else
      <div class="journal__grid">
        @foreach ($posts as $i => $post)
          @include('partials.post-card', ['post' => $post, 'delay' => $i * 100, 'reveal' => true])
        @endforeach
      </div>
    @endif
  </div>
</section>

<!-- ==================== FAQ ==================== -->
<section class="section faq" id="faq">
  <div class="shell">
    <p class="eyebrow eyebrow--center" data-reveal>{{ $content->text('faq.eyebrow') }}</p>
    <h2 data-reveal style="--d:60ms">{!! $content->html('faq.title') !!}</h2>

    {{-- The questions come from the Pages module. However many there are, the
         accordion draws them the same way - the markup per question is the
         design and does not change. --}}
    <div class="faq__wrap" data-reveal style="--d:120ms">
      @foreach ($content->items('faq.items') as $item)
        <div class="q">
          <button class="q__btn">{{ $item['question'] ?? '' }}<span class="q__sign"></span></button>
          <div class="q__panel"><p>{{ $item['answer'] ?? '' }}</p></div>
        </div>
      @endforeach
    </div>
  </div>
</section>

<!-- ==================== CTA ==================== -->
<section class="cta">
  <div class="shell cta__in">
    <span class="cta__tag" data-reveal><i></i>{{ $content->text('cta.tag') }}</span>
    <h2 data-reveal style="--d:80ms">{!! $content->html('cta.title') !!}</h2>
    <p data-reveal style="--d:160ms">
      {{ $content->text('cta.text') }}
    </p>
    <div class="cta__btns" data-reveal style="--d:240ms">
      <a href="{{ $content->text('cta.primary_link') }}" class="btn-a">{{ $content->text('cta.primary_label') }}</a>
      <a href="{{ $content->text('cta.secondary_link') }}" class="btn-outline">{{ $content->text('cta.secondary_label') }}</a>
    </div>
  </div>
</section>

<!-- ==================== BADGES ==================== -->
<div class="badges" aria-hidden="true">
  <div class="badges__track" id="badges">
    <div class="badges__group">
      <span class="badges__item">Batch tested</span>
      <span class="badges__item">Fragrance free</span>
      <span class="badges__item">Recyclable packaging</span>
      <span class="badges__item">Carbon-neutral delivery</span>
      <span class="badges__item">30-day returns</span>
      <span class="badges__item">Support 7 days a week</span>
    </div>
  </div>
</div>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/home.js') }}"></script>
@endpush
