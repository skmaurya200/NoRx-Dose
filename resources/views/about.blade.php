@extends('components.baselayout')

@section('title', 'About us - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/about.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0113 0"/><circle cx="17.5" cy="9" r="2.6"/><path d="M16 15.5a5.5 5.5 0 015.5 4.5"/></symbol>
      <symbol id="i-leaf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20C4 10 10 4 20 4c0 10-6 16-16 16z"/><path d="M4 20l9-9"/></symbol>
      <symbol id="i-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></symbol>
      <symbol id="i-flask" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7L4.5 19A2 2 0 006.2 22h11.6a2 2 0 001.7-3L14 9V2"/><path d="M9 2h6M7.5 15h9"/></symbol>
      <symbol id="i-recycle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 19H4l3-5M17 19h3l-2-3.5M12 3l3 5M7 19l-2-3.5 4.5-8L12 3M17 19l2-3.5-4-7"/></symbol>
      <symbol id="i-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 010 18 15 15 0 010-18z"/></symbol>
      <symbol id="i-bag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M6 6L5 2H2"/></symbol>
      <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></symbol>
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@section('content')
<!-- ==================== HERO ==================== -->
<section class="hero">
  <div class="figures" aria-hidden="true">
    <div class="fig"><i></i><b></b></div>
    <div class="fig"><i></i><b></b></div>
    <div class="fig"><i></i><b></b></div>
    <div class="fig"><i></i><b></b></div>
  </div>

  <div class="orb orb--a"><svg><use href="#i-users"/></svg></div>
  <div class="orb orb--b"><svg><use href="#i-leaf"/></svg></div>

  <div class="shell hero__in">
    <span class="hero__badge"><svg width="12" height="12"><use href="#i-leaf"/></svg>{{ $content->text('hero.badge') }}</span>
    <h1>{!! $content->html('hero.title') !!}</h1>
    <p class="hero__sub">{{ $content->text('hero.subtitle') }}</p>

    <div class="proof">
      <span class="stack"><span>A</span><span>M</span><span>K</span><span>R</span></span>
      {!! $content->html('proof.text') !!}
    </div>
  </div>
</section>

<!-- ==================== CHAPTERS ==================== -->
<section class="chap">
  <div class="shell">
    <div class="chap__bars" id="bars" role="tablist" aria-label="Chapters"></div>

    <div class="chap__head">
      <span class="chap__n" id="chapN">01</span>
      <span class="chap__label" id="chapLabel">Our story</span>
      <div class="arrows">
        <button class="arrow" id="prev" aria-label="Previous chapter">‹</button>
        <button class="arrow" id="next" aria-label="Next chapter">›</button>
      </div>
    </div>

    <article class="card" id="card"></article>
  </div>
</section>

<!-- ==================== QUOTES ==================== -->
<section class="quotes">
  <div class="shell">
    <div class="quotes__box" id="quotes">
      <div class="quotes__mark" aria-hidden="true"><span></span><span></span></div>

      {{-- One slide per quote from the Pages module. The initials in the
           circle are taken from the name rather than stored separately. --}}
      @foreach ($content->items('quotes.items') as $quote)
        @php
            $initials = collect(explode(' ', $quote['name'] ?? ''))
                ->filter()->take(2)
                ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
                ->implode('');
        @endphp
        <div @class(['slide', 'is-on' => $loop->first])>
          <span class="slide__av">{{ $initials }}</span>
          <div>
            <p class="slide__q">{{ $quote['quote'] ?? '' }}</p>
            <p class="slide__who"><b>{{ $quote['name'] ?? '' }}</b><span>{{ $quote['role'] ?? '' }}</span></p>
          </div>
        </div>
      @endforeach

      <div class="quotes__nav">
        <button class="qbtn" id="qPrev" aria-label="Previous quote">‹</button>
        <div class="dots" id="dots"></div>
        <button class="qbtn" id="qNext" aria-label="Next quote">›</button>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FAQ ==================== -->
<section class="faq">
  <div class="shell">
    <p class="eyebrow">{{ $content->text('faq.eyebrow') }}</p>
    <h2>{!! $content->html('faq.title') !!}</h2>

    @foreach ($content->items('faq.items') as $item)
      <div class="q">
        <button class="q__btn">{{ $item['question'] ?? '' }}<span class="q__sign"></span></button>
        <div class="q__body"><div class="q__inner"><p>{{ $item['answer'] ?? '' }}</p></div></div>
      </div>
    @endforeach
  </div>
</section>

<!-- ==================== CTA ==================== -->
<section class="cta">
  <div class="shell">
    <div class="cta__box">
      <div class="cta__l">
        <p class="eyebrow">{{ $content->text('cta.eyebrow') }}</p>
        <h2>{!! $content->html('cta.title') !!}</h2>
      </div>
      <div class="cta__btns">
        <a href="{{ route('shop') }}" class="btn-gold">{{ $content->text('cta.primary_label') }}</a>
        <a href="{{ route('reviews') }}" class="btn-light">{{ $content->text('cta.secondary_label') }}</a>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    {{-- The chapters, from the Pages module. The icons stay in the script:
         they belong to the position in the stepper, not to the words. --}}
    <script>window.AURUM_CHAPTERS = @json($content->items('chapters.items'));</script>
    <script src="{{ asset('js/pages/about.js') }}"></script>
@endpush
