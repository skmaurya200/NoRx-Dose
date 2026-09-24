@extends('components.baselayout')

@section('title', 'Your cart - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/cart.css') }}">
    <link rel="stylesheet" href="{{ asset('css/offers.css') }}">
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
      <symbol id="i-gift" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="9" width="18" height="12" rx="2"/><path d="M3 13h18M12 9v12"/><path d="M12 9S9.5 4 7.5 5.5 9 9 12 9zM12 9s2.5-5 4.5-3.5S15 9 12 9z"/></symbol>
      <symbol id="i-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="7.5" r="1.4"/></symbol>
      <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></symbol>
      <symbol id="i-recycle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 19H4l3-5M17 19h3l-2-3.5M12 3l3 5M7 19l-2-3.5 4.5-8L12 3M17 19l2-3.5-4-7"/></symbol>
      <symbol id="i-flask" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7L4.5 19A2 2 0 006.2 22h11.6a2 2 0 001.7-3L14 9V2"/><path d="M9 2h6M7.5 15h9"/></symbol>
      <symbol id="i-bolt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></symbol>
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@section('content')
<!-- ==================== CART ==================== -->
<main class="shell">
  <div class="wrap">

    <!-- ---- items ---- -->
    <section>
      <div class="head">
        <h1>{!! $content->html('page.title') !!}</h1>
        <span class="head__count" id="headCount">0 items</span>
        <button class="head__clear" id="clear">{{ $content->text('page.clear_label') }}</button>
      </div>

      <div class="items" id="items"></div>

      <a href="{{ route('shop') }}" class="back" id="back">{{ $content->text('page.back_label') }}</a>
    </section>

    <!-- ---- summary ---- -->
    <aside class="sum">
      <div class="sum__head">
        <h2>{{ $content->text('page.summary_title') }}</h2>
        <p>{{ $content->text('page.summary_sub') }}</p>
      </div>

      <div class="sum__body">
        {{-- Offers, not a text box. Every live public code the manager has set
             up is listed with its own Apply button, so nobody has to know a
             code exists or type it correctly. cart.js fills this in. --}}
        <div class="offers__head">
          <b>{{ $content->text('page.offers_label') }}</b>
          <span id="offersCount"></span>
        </div>

        <div class="offers" id="offers"></div>
        <p class="couponMsg" id="couponMsg" role="status"></p>

        <p class="sum__label">Shipping method</p>
        <div class="ship" id="ship" role="radiogroup" aria-label="Shipping method"></div>

        <div class="rows">
          <div class="row"><span id="subLabel">Subtotal</span><span id="subVal">$0.00</span></div>
          <div class="row row--off" id="offRow" style="display:none"><span id="offLabel">Discount</span><span id="offVal">−$0.00</span></div>
          <div class="row"><span>Shipping</span><span id="shipVal">$0.00</span></div>
        </div>

        <div class="total">
          <b>Total</b>
          <strong id="totalVal">$0.00</strong>
        </div>

        <button class="checkout" id="checkout" data-checkout-url="{{ route('checkout') }}">
          <svg><use href="#i-lock"/></svg>{{ $content->text('page.checkout_label') }}
        </button>

        <div class="badges">
          @foreach (['one' => 'i-lock', 'two' => 'i-recycle', 'three' => 'i-flask', 'four' => 'i-bolt'] as $slot => $icon)
            <span><svg><use href="#{{ $icon }}"/></svg>{{ $content->text('page.badge_'.$slot) }}</span>
          @endforeach
        </div>
      </div>
    </aside>

  </div>
</main>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    {{-- Rendered by the server so the offers are on screen at first paint
         rather than after a round trip. --}}
    <script>window.AURUM_OFFERS = @json($offers);</script>
    <script src="{{ asset('js/pages/cart.js') }}"></script>
@endpush
