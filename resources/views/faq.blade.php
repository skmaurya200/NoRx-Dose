@extends('components.baselayout')

@section('title', 'Help centre - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/faq.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></symbol>
      <symbol id="i-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15l6-6 6 6"/></symbol>
      <symbol id="i-leaf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20C4 10 10 4 20 4c0 10-6 16-16 16z"/><path d="M4 20l9-9"/></symbol>
      <symbol id="i-bag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M6 6L5 2H2"/></symbol>
      <symbol id="i-truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h11v10H2z"/><path d="M13 10h4l3 3v4h-7z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/></symbol>
      <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></symbol>
      <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/></symbol>
      <symbol id="i-star" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 .9-5 4.8 1.2 7-6.2-3.4L5.8 21 7 14.2 2 9.4l7-.9z"/></symbol>
      <symbol id="i-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="7.5" r="1.4"/></symbol>
      <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 6.5l9 6 9-6"/></symbol>
      <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-11.5 7.2L4 21l1.8-5.5A8 8 0 1121 12z"/></symbol>
      <symbol id="i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h4l2 5-2.5 1.5a12 12 0 006 6L16 13l5 2v4a2 2 0 01-2.2 2A17 17 0 013 5.2 2 2 0 015 3z"/></symbol>
      <symbol id="i-recycle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 19H4l3-5M17 19h3l-2-3.5M12 3l3 5M7 19l-2-3.5 4.5-8L12 3M17 19l2-3.5-4-7"/></symbol>
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@section('content')
<!-- ==================== HERO ==================== -->
<section class="hero">
  <div class="blob blob--a">FAQ</div>
  <div class="blob blob--b">FAQ</div>

  <div class="shell hero__in">
    <span class="hero__badge"><svg width="12" height="12"><use href="#i-chat"/></svg>{{ $content->text('hero.badge') }}</span>
    <h1>{!! $content->html('hero.title') !!}</h1>
    <p class="hero__sub">{{ $content->text('hero.subtitle') }}</p>

    <form class="big" id="searchForm" role="search">
      <svg><use href="#i-search"/></svg>
      <input type="search" id="faqQ" placeholder="{{ $content->text('search.placeholder') }}" aria-label="Search the help centre">
      <button type="submit">{{ $content->text('search.button') }}</button>
    </form>

    {{-- The shortcut buttons search for their own wording, so editing a label
         changes what it looks for - which is the only behaviour that could
         not surprise somebody. --}}
    <div class="quick">
      @foreach (['one' => 'i-shield', 'two' => 'i-truck', 'three' => 'i-recycle', 'four' => 'i-tag'] as $slot => $icon)
        @if ($content->has('search.quick_'.$slot))
          <button data-ask="{{ $content->text('search.quick_'.$slot) }}">
            <svg><use href="#{{ $icon }}"/></svg>{{ $content->text('search.quick_'.$slot) }}
          </button>
        @endif
      @endforeach
    </div>
  </div>
</section>

<!-- ==================== STATS ==================== -->
<section class="stats">
  <div class="shell">
    <div class="stats__card">
      @foreach (['one', 'two', 'three', 'four'] as $slot)
        <div class="stat">
          <b>{{ $content->text('stats.'.$slot.'_value') }}</b>
          <span>{{ $content->text('stats.'.$slot.'_label') }}</span>
        </div>
      @endforeach
    </div>
  </div>
</section>

<!-- ==================== TOPICS ==================== -->
<section class="topics">
  <div class="shell">
    <p class="eyebrow">{{ $content->text('topics.eyebrow') }}</p>
    <h2>{!! $content->html('topics.title') !!}</h2>
    <p>{{ $content->text('topics.subtitle') }}</p>
  </div>
</section>

<!-- ==================== ACCORDIONS ==================== -->
<section class="accs">
  <div class="shell" id="accs"></div>
</section>

<!-- ==================== HELP + TRUST ==================== -->
<section class="bottom">
  <div class="shell">
    <div class="bottom__grid">
      <div class="helpBox">
        <div class="helpBox__in">
          <h3>{{ $content->text('help.title') }}</h3>
          <p>{{ $content->text('help.text') }}</p>
          <div class="helpBox__row">
            <a href="mailto:{{ $site->text('contact.email') }}"><svg><use href="#i-mail"/></svg>{{ $content->text('help.link_one') }}</a>
            <a href="#"><svg><use href="#i-chat"/></svg>{{ $content->text('help.link_two') }}</a>
            @if ($site->has('contact.phone'))
              <a href="tel:{{ preg_replace('/[^\d+]/', '', $site->text('contact.phone')) }}"><svg><use href="#i-phone"/></svg>{{ $content->text('help.link_three') }}</a>
            @else
              <a href="#"><svg><use href="#i-phone"/></svg>{{ $content->text('help.link_three') }}</a>
            @endif
          </div>
        </div>
      </div>

      <div class="trust">
        <h4>{{ $content->text('help.trust_title') }}</h4>
        <div class="trust__grid">
          @foreach (['one' => 'i-lock', 'two' => 'i-shield', 'three' => 'i-recycle', 'four' => 'i-star'] as $slot => $icon)
            <span><svg><use href="#{{ $icon }}"/></svg>{{ $content->text('help.trust_'.$slot) }}</span>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    {{-- Every question from the Pages module, flat. The script groups them
         into accordions by topic, in the order the topics first appear. --}}
    <script>window.AURUM_FAQ = @json($content->items('questions.items'));</script>
    <script src="{{ asset('js/pages/faq.js') }}"></script>
@endpush
