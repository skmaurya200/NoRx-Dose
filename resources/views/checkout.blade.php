@extends('components.baselayout')

@section('title', 'Checkout - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/checkout.css') }}">
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
      <symbol id="i-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.6"/><path d="M4 21a8 8 0 0116 0"/></symbol>
      <symbol id="i-card" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/></symbol>
      <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></symbol>
      <symbol id="i-truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h11v10H2z"/><path d="M13 10h4l3 3v4h-7z"/><circle cx="6" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/></symbol>
      <symbol id="i-tag" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="7.5" r="1.4"/></symbol>
      <symbol id="i-box" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M3 7l9 5 9-5M12 12v10"/></symbol>
    </svg>

    <!-- ==================== TICKER ==================== -->
@endsection

@section('content')
<!-- ==================== CHECKOUT ==================== -->
<main class="shell">
  <form class="wrap" id="checkoutForm" novalidate>

    <!-- ---------- LEFT ---------- -->
    <div>

      <!-- billing -->
      <section class="card">
        <div class="card__head">
          <span class="card__ic"><svg><use href="#i-user"/></svg></span>
          <div>
            <h2>{{ $content->text('page.billing_title') }}</h2>
            <p>All fields marked <i style="color:var(--bad);font-style:normal">*</i> are required</p>
          </div>
        </div>

        <div class="grid2">
          <div class="field">
            <label for="fname">First name <i>*</i></label>
            <input id="fname" name="first_name" type="text" placeholder="First name" maxlength="80" autocomplete="given-name" data-req>
            <p class="err">Please enter your first name.</p>
          </div>
          <div class="field">
            <label for="lname">Last name <i>*</i></label>
            <input id="lname" name="last_name" type="text" placeholder="Last name" maxlength="80" autocomplete="family-name" data-req>
            <p class="err">Please enter your last name.</p>
          </div>
        </div>

        <div class="grid2">
          <div class="field">
            <label for="phone">Phone <i>*</i></label>
            <input id="phone" name="phone" type="tel" placeholder="Phone number" maxlength="32" autocomplete="tel" data-req data-phone>
            <p class="err">Please enter a phone number.</p>
          </div>
          <div class="field">
            <label for="email">Email address <i>*</i></label>
            <input id="email" name="email" type="email" placeholder="Email address" maxlength="180" autocomplete="email" data-req data-email>
            <p class="err">Please enter a valid email address.</p>
          </div>
        </div>

        <div class="grid3">
          {{-- One country, because the store ships to one country. The value
               is what PlaceOrderRequest checks against config('shop.country'),
               so the field cannot offer anything the order would be refused
               for. --}}
          <div class="field">
            <label for="country">Country <i>*</i></label>
            <select id="country" name="country" data-req>
              <option value="{{ $country }}" selected>{{ $countryName }}</option>
            </select>
            <p class="err">Please choose a country.</p>
            <p class="hint">We currently ship within the United States only.</p>
          </div>
          <div class="field">
            <label for="state">State <i>*</i></label>
            <select id="state" name="state" data-req>
              <option value="">Select state</option>
              @foreach ($states as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
              @endforeach
            </select>
            <p class="err">Please choose a state.</p>
          </div>
          <div class="field">
            <label for="zip">ZIP code <i>*</i></label>
            <input id="zip" name="postal_code" type="text" inputmode="numeric"
                   placeholder="94107" maxlength="10" autocomplete="postal-code" data-req data-zip>
            <p class="err">Enter a US ZIP code, e.g. 94107 or 94107-1234.</p>
          </div>
        </div>

        <div class="field">
          <label for="street">Street address <i>*</i></label>
          <input id="street" name="street" type="text" placeholder="House number and street name" maxlength="200" autocomplete="street-address" data-req>
          <p class="err">Please enter your street address.</p>
        </div>

        <div class="grid2">
          <div class="field">
            <label for="city">City <i>*</i></label>
            <input id="city" name="city" type="text" placeholder="City" maxlength="100" autocomplete="address-level2" data-req>
            <p class="err">Please enter your city.</p>
          </div>
          <div class="field">
            <label for="notes">Order notes</label>
            <textarea id="notes" name="notes" maxlength="1000" placeholder="Anything we should know about delivery"></textarea>
          </div>
        </div>

        <div class="note">
          <svg><use href="#i-lock"/></svg>
          Your details are sent over an encrypted connection and are never shared with third parties.
        </div>
      </section>

      <!-- payment -->
      <section class="card">
        <div class="card__head">
          <span class="card__ic"><svg><use href="#i-card"/></svg></span>
          <div>
            <h2>{{ $content->text('page.payment_title') }}</h2>
            <p>Encrypted checkout</p>
          </div>
        </div>

        <div class="payRow">
          <svg><use href="#i-card"/></svg>
          <b>Credit or debit card</b>
          <span class="brands"><span>VISA</span><span>MC</span><span>AMEX</span></span>
        </div>

        <div class="payBox">
          <div class="field">
            <label for="holder">Card holder <i>*</i></label>
            <input id="holder" name="card_holder" type="text" placeholder="Name as printed on the card" maxlength="120" autocomplete="cc-name" data-req>
            <p class="err">Please enter the card holder name.</p>
          </div>

          <div class="field">
            <label for="cardno">Card number <i>*</i></label>
            <input id="cardno" name="card_number" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="•••• •••• •••• ••••" maxlength="23" data-req data-card>
            <p class="err">Please enter a valid card number.</p>
          </div>

          <div class="grid2">
            <div class="field">
              <label for="exp">Expiry (MM/YY) <i>*</i></label>
              <input id="exp" name="card_expiry" type="text" inputmode="numeric" autocomplete="cc-exp" placeholder="MM / YY" maxlength="7" data-req data-exp>
              <p class="err">Please enter a valid expiry date.</p>
            </div>
            <div class="field">
              <label for="cvc">Card code <i>*</i></label>
              <input id="cvc" name="card_cvc" type="text" inputmode="numeric" autocomplete="cc-csc" placeholder="CVC" maxlength="4" data-req data-cvc>
              <p class="err">Please enter the code on the back of your card.</p>
            </div>
          </div>
        </div>

        <div class="note">
          <svg><use href="#i-lock"/></svg>
          Your card number is encrypted before it is stored, and your security code is
          checked and then discarded — we never keep it.
        </div>
      </section>
    </div>

    <!-- ---------- RIGHT ---------- -->
    <aside class="sum">
      <div class="sum__head">
        <h2>{{ $content->text('page.summary_title') }}</h2>
        <a href="{{ route('cart') }}">Edit cart</a>
      </div>

      <div id="lines"></div>

      <div class="sum__body">
        <p class="sum__label"><svg><use href="#i-truck"/></svg>Shipping method</p>
        <div class="ship" id="ship" role="radiogroup" aria-label="Shipping method"></div>

        {{-- The same one-tap offers as the cart. Whatever was applied there
             carries across, and it can be changed here without typing. --}}
        <div class="offers__head">
          <b>Available offers</b>
          <span id="offersCount"></span>
        </div>

        <div class="offers" id="offers"></div>
        <p class="couponMsg" id="couponMsg" role="status"></p>

        <div class="rows">
          <div class="row"><span id="subLabel">Subtotal</span><span id="subVal">$0.00</span></div>
          <div class="row row--off" id="offRow" style="display:none"><span id="offLabel">Discount</span><span id="offVal">−$0.00</span></div>
          <div class="row"><span>Shipping</span><span id="shipVal">$0.00</span></div>
        </div>

        <div class="total">
          <b>Total</b>
          <strong id="totalVal">$0.00</strong>
        </div>

        <p class="formErr" id="formErr" role="alert"></p>

        <button type="submit" class="place" id="place">
          <svg><use href="#i-lock"/></svg>Place order securely
        </button>
      </div>
    </aside>

  </form>
</main>

<!-- ==================== STICKY BAR ==================== -->
<div class="sticky" id="sticky">
  <div class="shell sticky__in">
    <div class="sticky__tot">
      <span>Order total</span>
      <b id="sTotal">$0.00</b>
    </div>
    <div class="sticky__chips">
      <span><svg><use href="#i-truck"/></svg><span id="sShip">Express</span></span>
      <span><svg><use href="#i-box"/></svg><span id="sItems">0 items</span></span>
      <span><svg><use href="#i-lock"/></svg>Encrypted checkout</span>
    </div>
    <button class="sticky__btn" id="sPlace"><svg><use href="#i-lock"/></svg>Place order securely →</button>
  </div>
</div>

<!-- ==================== FOOTER ==================== -->
@endsection

@push('scripts')
    <script>window.AURUM_OFFERS = @json($offers);</script>
    <script src="{{ asset('js/pages/checkout.js') }}"></script>
@endpush
