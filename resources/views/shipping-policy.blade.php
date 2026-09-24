@extends('components.baselayout')

@section('title', 'Shipping policy - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/shipping-policy.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="i-truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h11v10H2z"/><path d="M13 10h4l3 3v4h-7z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/></symbol>
      <symbol id="i-box" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M3 7l9 5 9-5M12 12v10"/></symbol>
      <symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></symbol>
      <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></symbol>
      <symbol id="i-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></symbol>
      <symbol id="i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
      <symbol id="i-leaf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20C4 10 10 4 20 4c0 10-6 16-16 16z"/><path d="M4 20l9-9"/></symbol>
      <symbol id="i-doc" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6v20h12V6z"/><path d="M14 2v4h4M9 12h6M9 16h6"/></symbol>
      <symbol id="i-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 01-11.5 7.2L4 21l1.8-5.5A8 8 0 1121 12z"/></symbol>
      <symbol id="i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 6.5l9 6 9-6"/></symbol>
      <symbol id="i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h4l2 5-2.5 1.5a12 12 0 006 6L16 13l5 2v4a2 2 0 01-2.2 2A17 17 0 013 5.2 2 2 0 015 3z"/></symbol>
      <symbol id="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/></symbol>
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@section('content')
<!-- ==================== HERO ==================== -->
<section class="hero">
  <div class="orb orb--a"><svg><use href="#i-truck"/></svg></div>
  <div class="orb orb--b"><svg><use href="#i-box"/></svg></div>

  <div class="shell hero__in">
    <span class="hero__badge"><svg width="12" height="12"><use href="#i-box"/></svg>{{ $content->text('hero.badge') }}</span>
    <h1>{!! $content->html('hero.title') !!}</h1>
    <p class="hero__sub">{{ $content->text('hero.subtitle') }}</p>

    <div class="pills">
      @foreach (['one' => 'i-leaf', 'two' => 'i-bolt', 'three' => 'i-pin', 'four' => 'i-shield'] as $slot => $icon)
        @if ($content->has('pills.'.$slot))
          <span class="pill"><svg><use href="#{{ $icon }}"/></svg>{{ $content->text('pills.'.$slot) }}</span>
        @endif
      @endforeach
    </div>

    <div class="hero__cta">
      <a href="{{ route('shop') }}" class="btn-dark">{{ $content->text('pills.primary_label') }}</a>
      <a href="{{ route('all-products') }}" class="btn-outline">{{ $content->text('pills.secondary_label') }}</a>
    </div>
  </div>
</section>

<!-- ==================== TABS ==================== -->
<section class="tabs">
  <div class="shell">
    <div class="tabs__row" role="tablist" aria-label="Shipping information">
      <button class="tab is-on" role="tab" aria-selected="true"  aria-controls="p1" id="t1"><svg><use href="#i-clock"/></svg>Overview</button>
      <button class="tab"       role="tab" aria-selected="false" aria-controls="p2" id="t2"><svg><use href="#i-truck"/></svg>Order journey</button>
      <button class="tab"       role="tab" aria-selected="false" aria-controls="p3" id="t3"><svg><use href="#i-bolt"/></svg>Delivery speeds</button>
      <button class="tab"       role="tab" aria-selected="false" aria-controls="p4" id="t4"><svg><use href="#i-doc"/></svg>Policy details</button>
      <button class="tab"       role="tab" aria-selected="false" aria-controls="p5" id="t5"><svg><use href="#i-chat"/></svg>FAQs</button>
    </div>

    <!-- ---- 1. OVERVIEW ---- -->
    <div class="panel is-on" id="p1" role="tabpanel" aria-labelledby="t1">
      <p class="panel__eyebrow">{{ $content->text('overview.eyebrow') }}</p>
      <h2>{!! $content->html('overview.title') !!}</h2>
      <p class="panel__sub">{{ $content->text('overview.subtitle') }}</p>

      {{-- The icons belong to the position rather than to the words. The last
           tag is set in gold, as the design does. --}}
      @php $factIcons = ['i-sun', 'i-box', 'i-bolt', 'i-shield']; @endphp

      <div class="facts">
        @foreach ($content->items('overview.items') as $fact)
          <div class="fact">
            <span class="fact__ic"><svg><use href="#{{ $factIcons[$loop->index % count($factIcons)] }}"/></svg></span>
            <div class="fact__txt">
              <h3>{{ $fact['title'] ?? '' }}</h3>
              <p>{{ $fact['text'] ?? '' }}</p>
            </div>
            <span @class(['fact__tag', 'fact__tag--gold' => $loop->last])>{{ $fact['tag'] ?? '' }}</span>
          </div>
        @endforeach
      </div>
    </div>

    <!-- ---- 2. ORDER JOURNEY ---- -->
    <div class="panel" id="p2" role="tabpanel" aria-labelledby="t2">
      <p class="panel__eyebrow">{{ $content->text('journey.eyebrow') }}</p>
      <h2>{!! $content->html('journey.title') !!}</h2>
      <p class="panel__sub">{{ $content->text('journey.subtitle') }}</p>

      <div class="steps">
        @foreach ($content->items('journey.items') as $step)
          <div class="step">
            <span class="step__n">{{ $loop->iteration }}</span>
            <div class="step__b">
              <h3>{{ $step['title'] ?? '' }}</h3>
              <p>{{ $step['text'] ?? '' }}</p>
              <p class="step__when">{{ $step['when'] ?? '' }}</p>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    <!-- ---- 3. DELIVERY SPEEDS ---- -->
    <div class="panel" id="p3" role="tabpanel" aria-labelledby="t3">
      <p class="panel__eyebrow">{{ $content->text('speeds.eyebrow') }}</p>
      <h2>{!! $content->html('speeds.title') !!}</h2>
      <p class="panel__sub">{{ $content->text('speeds.subtitle') }}</p>

      {{-- Copy only. What a customer is actually charged comes from the
           shipping methods in config/shop.php, and the checkout re-derives it
           from there rather than from anything on this page. --}}
      <table class="tbl">
        <thead>
          <tr><th>Method</th><th>Arrives in</th><th>Tracking</th><th>Cost</th></tr>
        </thead>
        <tbody>
          @foreach ($content->items('speeds.items') as $row)
            <tr>
              <td data-th="Method"><b>{{ $row['method'] ?? '' }}</b><small>{{ $row['method_note'] ?? '' }}</small></td>
              <td data-th="Arrives in">{{ $row['arrives'] ?? '' }}</td>
              <td data-th="Tracking">{{ $row['tracking'] ?? '' }}</td>
              <td data-th="Cost"><span class="money">{{ $row['cost'] ?? '' }}</span><small>{{ $row['cost_note'] ?? '' }}</small></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <!-- ---- 4. POLICY DETAILS ---- -->
    <div class="panel" id="p4" role="tabpanel" aria-labelledby="t4">
      <p class="panel__eyebrow">{{ $content->text('policy.eyebrow') }}</p>
      <h2>{!! $content->html('policy.title') !!}</h2>
      <p class="panel__sub">{{ $content->text('policy.subtitle') }}</p>

      <div class="blocks">
        @foreach ($content->items('policy.items') as $block)
          <div class="block">
            <h3>{{ $block['title'] ?? '' }}</h3>
            <p>{{ $block['text'] ?? '' }}</p>
          </div>
        @endforeach
      </div>
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

<!-- ==================== HELP BAND ==================== -->
<section class="help">
  <div class="shell">
    <div class="help__box">
      <div class="help__head">
        <p class="panel__eyebrow">{{ $content->text('help.eyebrow') }}</p>
        <h2>{!! $content->html('help.title') !!}</h2>
      </div>

      <div class="ways">
        {{-- Straight from Settings, so the address in the footer and the one
             here cannot disagree. --}}
        <div class="way">
          <span class="way__ic"><svg><use href="#i-mail"/></svg></span>
          <div><b>Email</b><span>{{ $site->text('contact.email') }}</span></div>
        </div>
        <div class="way">
          <span class="way__ic"><svg><use href="#i-chat"/></svg></span>
          <div><b>Live chat</b><span>{{ $site->text('contact.hours') }}</span></div>
        </div>
        @if ($site->has('contact.phone'))
          <div class="way">
            <span class="way__ic"><svg><use href="#i-phone"/></svg></span>
            <div><b>Phone</b><span>{{ $site->text('contact.phone') }}</span></div>
          </div>
        @endif
      </div>

      <div class="help__cta">
        <a href="mailto:{{ $site->text('contact.email') }}" class="btn-outline">{{ $content->text('help.primary_label') }}</a>
        <a href="{{ route('shop') }}" class="btn-gold">{{ $content->text('help.secondary_label') }}</a>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/shipping-policy.js') }}"></script>
@endpush
