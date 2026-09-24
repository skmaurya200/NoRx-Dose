@extends('components.baselayout')

@section('title', 'Order '.$order->order_number.' - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/order-confirmation.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="i-bag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M6 6L5 2H2"/></symbol>
      <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></symbol>
      <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5.5 5.5L20 6.5"/></symbol>
      <symbol id="i-truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h11v10H2z"/><path d="M13 10h4l3 3v4h-7z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/></symbol>
      <symbol id="i-card" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/></symbol>
      <symbol id="i-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-6.3 7-11.5A7 7 0 005 10.5C5 15.7 12 22 12 22z"/><circle cx="12" cy="10.5" r="2.6"/></symbol>
      <symbol id="i-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="7.5" r="1.4"/></symbol>
      <symbol id="jar" viewBox="0 0 120 150">
        <rect x="44" y="6" width="32" height="16" rx="4" fill="#C9A227"/>
        <rect x="26" y="30" width="68" height="106" rx="12" fill="#fff" stroke="#E7E2D6" stroke-width="2"/>
        <rect x="34" y="52" width="52" height="44" rx="6" fill="#F7EFD8"/>
      </symbol>
    </svg>
@endsection

@section('content')
<main class="shell oc">

  <header class="oc__head">
    <span class="oc__tick"><svg><use href="#i-check"/></svg></span>
    <h1>Thank you, <em>{{ $order->first_name }}</em></h1>
    <p>
      Your order is confirmed. 
      <!-- We have emailed a receipt to
      <b>{{ $order->email }}</b>. -->
    </p>
    <p class="oc__num">
      Order <b>{{ $order->order_number }}</b>
      &middot; placed {{ $order->placed_at?->format('j F Y, H:i') }}
    </p>
    {{-- The page is addressed by an unguessable token, so this link is the
         customer's only way back to it. Worth saying so plainly. --}}
    <p class="oc__keep">Keep this page bookmarked — it is your order record.</p>
  </header>

  <div class="oc__grid">

    <section class="oc__card">
      <h2><svg><use href="#i-bag"/></svg>What you ordered</h2>

      <div class="oc__lines">
        @foreach ($order->items as $item)
          <div class="oc__line">
            <span class="oc__media">
              <img src="{{ $item->product?->thumbnailUrl() ?? \App\Support\DefaultImage::product() }}"
                   alt="{{ $item->name }}" loading="lazy">
            </span>
            <span class="oc__lt">
              <b>{{ $item->displayName() }}</b>
              <span>{{ $order->money($item->unit_price) }} × {{ $item->quantity }}</span>
            </span>
            <span class="oc__lp">{{ $order->money($item->line_total) }}</span>
          </div>
        @endforeach
      </div>

      <dl class="oc__rows">
        <div><dt>Subtotal</dt><dd>{{ $order->money($order->subtotal) }}</dd></div>

        @if ((float) $order->discount_total > 0)
          <div class="oc__off">
            <dt>
              <svg><use href="#i-tag"/></svg>
              {{ $order->coupon_code }}
              @if ($order->coupon_description)
                <span>{{ $order->coupon_description }}</span>
              @endif
            </dt>
            <dd>&minus;{{ $order->money($order->discount_total) }}</dd>
          </div>
        @endif

        <div>
          <dt>Shipping — {{ $order->shipping_method_label }}</dt>
          <dd>{{ (float) $order->shipping_total > 0 ? $order->money($order->shipping_total) : 'Free' }}</dd>
        </div>
      </dl>

      <div class="oc__total">
        <b>Total paid</b>
        <strong>{{ $order->money($order->grand_total) }}</strong>
      </div>
    </section>

    <aside class="oc__side">
      <section class="oc__card">
        <h2><svg><use href="#i-pin"/></svg>Billing details</h2>
        <address>
          <b>{{ $order->customerName() }}</b>
          {{ $order->street }}<br>
          {{ $order->city }}, {{ $order->state }} {{ $order->postal_code }}<br>
          {{ $order->stateName() }}, {{ $order->country }}
          <span>{{ $order->phone }}</span>
        </address>
      </section>

      <section class="oc__card">
        <h2><svg><use href="#i-truck"/></svg>Delivery</h2>
        <p class="oc__p">{{ $order->shipping_method_label }}</p>
        @if ($order->notes)
          <p class="oc__note"><b>Your note:</b> {{ $order->notes }}</p>
        @endif
      </section>

      @if ($order->payment)
        <section class="oc__card">
          <h2><svg><use href="#i-card"/></svg>Payment</h2>
          <p class="oc__p">{{ $order->payment->maskedNumber() }}</p>
          <p class="oc__small">
            Charged {{ $order->money($order->payment->amount) }} ·
            {{ ucfirst($order->payment_status) }}
          </p>
        </section>
      @endif
    </aside>
  </div>

  <div class="oc__actions">
    <a href="{{ route('shop') }}" class="oc__btn">Continue shopping</a>
    <a href="{{ route('home') }}" class="oc__link">Back to home</a>
  </div>

</main>
@endsection
