@extends('components.baselayout')

@section('title', 'Reviews - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/reviews.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="i-star" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 .9-5 4.8 1.2 7-6.2-3.4L5.8 21 7 14.2 2 9.4l7-.9z"/></symbol>
      <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></symbol>
      <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/></symbol>
      <symbol id="i-medal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="15" r="6"/><path d="M8.5 9.5L6 2h12l-2.5 7.5"/></symbol>
      <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-11.5 7.2L4 21l1.8-5.5A8 8 0 1121 12z"/></symbol>
      <symbol id="i-pen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4l10-10-4-4L4 16z"/><path d="M13.5 6.5l4 4"/></symbol>
      <symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></symbol>
      <symbol id="i-leaf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20C4 10 10 4 20 4c0 10-6 16-16 16z"/><path d="M4 20l9-9"/></symbol>
      <symbol id="i-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5 5 0 00-7.1 0L12 7.3l-1.7-1.7a5 5 0 10-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 000-7.1z"/></symbol>
      <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 6.5l9 6 9-6"/></symbol>
      <symbol id="i-bag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M6 6L5 2H2"/></symbol>
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@php
    // The hero figures the summary does not already carry. $recommend is null
    // when there is nothing to divide by, so the page makes no claim at all
    // rather than a confident 0%.
    $bars = collect($summary['bars']);
    $fiveStar = (int) ($bars->firstWhere('rating', 5)['count'] ?? 0);
    $fourPlus = $fiveStar + (int) ($bars->firstWhere('rating', 4)['count'] ?? 0);
    $recommend = $summary['total'] > 0 ? (int) round($fourPlus / $summary['total'] * 100) : null;
    $verified = $reviews->where('is_verified', true)->count();
    $stars = fn (int $n) => str_repeat('★', max(0, min(5, $n))).str_repeat('☆', 5 - max(0, min(5, $n)));
@endphp

@section('content')
<!-- ==================== HERO ==================== -->
<section class="hero">
  <div class="planet planet--a"><div class="planet__ball"></div><div class="planet__ring"></div></div>
  <div class="planet planet--b"><div class="planet__ball"></div><div class="planet__ring"></div></div>

  <div class="shell hero__in">
    <span class="hero__badge"><svg width="12" height="12"><use href="#i-star"/></svg>{{ $content->text('hero.badge') }}</span>
    <h1>{!! $content->html('hero.title') !!}</h1>
    <p class="hero__sub">{{ $content->text('hero.subtitle') }}</p>

    <div class="pills">
      @if ($summary['total'] > 0)
        <span class="pill"><svg><use href="#i-star"/></svg>{{ number_format($summary['average'], 1) }} average rating</span>
        <span class="pill"><svg><use href="#i-check"/></svg>{{ number_format($summary['total']) }} published {{ Str::plural('review', $summary['total']) }}</span>
      @endif
      <span class="pill"><svg><use href="#i-shield"/></svg>Every review read before it is published</span>
      @if ($recommend !== null)
        <span class="pill"><svg><use href="#i-heart"/></svg>{{ $recommend }}% rate us four stars or more</span>
      @endif
    </div>

    {{-- Counted up by the page script. Every figure below is a real count from
         the reviews table - there is nothing here that is not measured. --}}
    <div class="hstats">
      <div class="hstat"><b data-count="{{ $summary['total'] }}" data-comma>0</b><span>Published reviews</span></div>
      <div class="hstat"><b data-count="{{ $verified }}" data-comma>0</b><span>Verified purchases</span></div>
      <div class="hstat"><b data-count="{{ $recommend ?? 0 }}" data-suffix="%">0</b><span>Four stars or more</span></div>
      <div class="hstat"><b data-count="{{ $fiveStar }}" data-comma>0</b><span>Five-star reviews</span></div>
    </div>
  </div>
</section>

<!-- ==================== FEATURED ==================== -->
@if ($featured)
<section class="feat">
  <div class="shell">
    <div class="feat__box">
      <div class="feat__main">
        <span class="feat__tag"><svg width="11" height="11"><use href="#i-star"/></svg>Featured review</span>
        <div class="feat__stars">{{ $featured->stars() }}</div>
        <p class="feat__quote">{{ $featured->body }}</p>
        <div class="who">
          <span class="who__av">{{ $featured->initials() }}</span>
          <div><b>{{ $featured->author_name }}</b><span>{{ $featured->byline() }}</span></div>
        </div>
      </div>

      <div class="feat__side">
        <div class="mini">
          <svg><use href="#i-shield"/></svg>
          <b>{{ $featured->is_verified ? 'Verified purchase' : 'Published review' }}</b>
          <span>{{ $featured->is_verified ? 'Matched to an order we shipped' : 'Read and approved before publishing' }}</span>
        </div>
        <div class="mini">
          <svg><use href="#i-medal"/></svg>
          <b>{{ $featured->categoryLabel() }}</b>
          <span>{{ $featured->product?->name ?? 'About the shop' }}</span>
        </div>
      </div>
    </div>
  </div>
</section>
@endif

<!-- ==================== TABS ==================== -->
<section class="tabs">
  <div class="shell">
    <div class="tabs__row" role="tablist" aria-label="Review sections">
      <button class="tab is-on" role="tab" aria-selected="true"  aria-controls="p1" id="t1"><svg><use href="#i-chat"/></svg>Customer reviews</button>
      <button class="tab"       role="tab" aria-selected="false" aria-controls="p2" id="t2"><svg><use href="#i-star"/></svg>Ratings</button>
      <button class="tab"       role="tab" aria-selected="false" aria-controls="p3" id="t3"><svg><use href="#i-shield"/></svg>Why choose us</button>
      <button class="tab"       role="tab" aria-selected="false" aria-controls="p4" id="t4"><svg><use href="#i-pen"/></svg>Write a review</button>
      <button class="tab"       role="tab" aria-selected="false" aria-controls="p5" id="t5"><svg><use href="#i-medal"/></svg>FAQs</button>
    </div>

    <!-- ---- 1. REVIEWS ---- -->
    <div class="panel is-on" id="p1" role="tabpanel" aria-labelledby="t1">
      <p class="panel__eyebrow">{{ $content->text('list.eyebrow') }}</p>
      <h2>{!! $content->html('list.title') !!}</h2>
      <p class="panel__sub">{{ $content->text('list.subtitle') }}</p>

      <div class="chips" id="chips">
        <button class="chip is-on" data-cat="all">All reviews</button>
        @foreach (\App\Models\Review::CATEGORIES as $key => $label)
          <button class="chip" data-cat="{{ $key }}">{{ $label }}</button>
        @endforeach
      </div>

      <div class="reviews" id="reviews"></div>

      <div class="loadWrap"><button class="btn-ghost" id="load">{{ $content->text('list.load_label') }}</button></div>
    </div>

    <!-- ---- 2. RATINGS ---- -->
    <div class="panel" id="p2" role="tabpanel" aria-labelledby="t2">
      <p class="panel__eyebrow">{{ $content->text('list.ratings_eyebrow') }}</p>
      <h2>{!! $content->html('list.ratings_title') !!}</h2>
      <p class="panel__sub">{{ $content->text('list.ratings_subtitle') }}</p>

      <div class="ratings">
        <div class="score">
          <b>{{ number_format($summary['average'], 1) }}</b>
          <div class="st">{{ $stars((int) round($summary['average'])) }}</div>
          <span>from {{ number_format($summary['total']) }} {{ Str::plural('review', $summary['total']) }}</span>
        </div>

        <ul class="bars" id="bars">
          @foreach ($summary['bars'] as $bar)
            <li>
              <span class="lbl">{{ $bar['rating'] }} star</span>
              <span class="track"><span class="fill" data-w="{{ $bar['percent'] }}"></span></span>
              <span class="pct">{{ $bar['percent'] }}%</span>
            </li>
          @endforeach
        </ul>
      </div>

      @if ($categories)
        <div class="breakdown">
          @foreach ($categories as $category)
            <div class="bd"><b>{{ number_format($category['average'], 1) }}</b><span>{{ $category['label'] }}</span></div>
          @endforeach
        </div>
      @endif
    </div>

    <!-- ---- 3. WHY ---- -->
    <div class="panel" id="p3" role="tabpanel" aria-labelledby="t3">
      <p class="panel__eyebrow">{{ $content->text('why.eyebrow') }}</p>
      <h2>{!! $content->html('why.title') !!}</h2>
      <p class="panel__sub">{{ $content->text('why.subtitle') }}</p>

      {{-- The icons belong to the position rather than to the words, so they
           stay here and cycle however many blocks there are. --}}
      @php
          $whyIcons = ['i-shield', 'i-bolt', 'i-leaf', 'i-chat', 'i-check', 'i-heart'];
      @endphp

      <div class="blocks">
        @foreach ($content->items('why.items') as $block)
          <div class="block">
            <span class="block__ic"><svg><use href="#{{ $whyIcons[$loop->index % count($whyIcons)] }}"/></svg></span>
            <h3>{{ $block['title'] ?? '' }}</h3>
            <p>{{ $block['text'] ?? '' }}</p>
          </div>
        @endforeach
      </div>
    </div>

    <!-- ---- 4. WRITE ---- -->
    <div class="panel" id="p4" role="tabpanel" aria-labelledby="t4">
      <p class="panel__eyebrow">{{ $content->text('form.eyebrow') }}</p>
      <h2>{!! $content->html('form.title') !!}</h2>
      <p class="panel__sub">{{ $content->text('form.subtitle') }}</p>

      <form class="form" id="revForm" novalidate>
        <div class="field">
          <label for="stars">Your rating</label>
          <div class="picker" id="picker" role="radiogroup" aria-label="Your rating">
            <button type="button" data-v="1" aria-label="1 star">★</button>
            <button type="button" data-v="2" aria-label="2 stars">★</button>
            <button type="button" data-v="3" aria-label="3 stars">★</button>
            <button type="button" data-v="4" aria-label="4 stars">★</button>
            <button type="button" data-v="5" aria-label="5 stars">★</button>
            <span class="picker__out" id="pickerOut">Not rated yet</span>
          </div>
        </div>

        <div class="row2">
          <div class="field">
            <label for="rname">Your name</label>
            <input id="rname" type="text" placeholder="How it appears on the review" required>
          </div>
          <div class="field">
            <label for="remail">Email</label>
            <input id="remail" type="email" placeholder="Not shown publicly" required>
          </div>
        </div>

        <div class="field">
          <label for="rcat">What is this about?</label>
          <select id="rcat">
            @foreach (\App\Models\Review::CATEGORIES as $key => $label)
              <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label for="rtext">Your review</label>
          <textarea id="rtext" placeholder="What did you order, and how did it go?" required></textarea>
        </div>

        <button type="submit" class="btn-gold">{{ $content->text('form.button') }}</button>
        <p class="formOk" id="formOk" role="status"></p>
      </form>
    </div>

    <!-- ---- 5. FAQS ---- -->
    <div class="panel" id="p5" role="tabpanel" aria-labelledby="t5">
      <p class="panel__eyebrow">{{ $content->text('faq.eyebrow') }}</p>
      <h2>{!! $content->html('faq.title') !!}</h2>
      <p class="panel__sub">{{ $content->text('faq.subtitle') }}</p>

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
  <div class="shell">
    <div class="cta__box">
      <div class="cta__l">
        <p class="panel__eyebrow">{{ $content->text('cta.eyebrow') }}</p>
        @if ($summary['total'] > 0)
          <h2>Join <em>{{ number_format($summary['total']) }}</em> {{ Str::plural('customer', $summary['total']) }} who told us how it went</h2>
        @else
          <h2>Be the <em>first</em> to tell us how it went</h2>
        @endif
        <p>{{ $content->text('cta.text') }}</p>
        <div class="cta__btns">
          <a href="mailto:{{ $site->text('contact.email') }}" class="btn-outline">{{ $content->text('cta.primary_label') }}</a>
          <a href="{{ route('shop') }}" class="btn-gold">{{ $content->text('cta.secondary_label') }}</a>
        </div>
      </div>

      <div class="ways">
        <div class="way">
          <span class="way__ic"><svg><use href="#i-bag"/></svg></span>
          <div><b>{{ $content->text('cta.way_one') }}</b><span>{{ $content->text('cta.way_one_text') }}</span></div>
        </div>
        <div class="way">
          <span class="way__ic"><svg><use href="#i-chat"/></svg></span>
          <div><b>{{ $content->text('cta.way_two') }}</b><span>{{ $content->text('cta.way_two_text') }}</span></div>
        </div>
        {{-- The address itself comes from Settings, so it is right in one
             place rather than typed again here. --}}
        <div class="way">
          <span class="way__ic"><svg><use href="#i-mail"/></svg></span>
          <div><b>{{ $content->text('cta.way_three') }}</b><span>{{ $site->text('contact.email') }}</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    {{-- Approved reviews only, and only the fields a visitor may see - the
         service strips the email address and the moderation trail before this
         ever reaches the page. --}}
    <script>window.AURUM_REVIEWS = @json($reviewsJson);</script>
    <script src="{{ asset('js/pages/reviews.js') }}"></script>
@endpush
