{{--
    Create / edit a discount code. One form serves both; the endpoint and the
    button label are the only difference.

    The browser hints here are a convenience - StoreCouponRequest is what
    actually decides, and its errors are painted under each input.
--}}
@extends('manager.components.layout')

@section('title', $isEdit ? 'Edit code' : 'New code')

@php
    $symbol = config('shop.currency_symbol', '$');
    $localDate = fn ($value) => $value?->format('Y-m-d\TH:i');
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $isEdit ? 'Edit discount code' : 'New discount code' }}</h1>
            <p class="ph-sub">
                {{ $isEdit
                    ? 'Changes apply to the next order, not to orders already placed.'
                    : 'Set the discount and the spend it unlocks at. Customers apply it with one tap.' }}
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.coupons.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to codes
            </a>
        </div>
    </div>

    <form
        data-api-form="{{ $isEdit
            ? route('api.manager.coupons.update', $coupon)
            : route('api.manager.coupons.store') }}"
        data-method="POST"
        data-redirect="{{ route('manager.coupons.index') }}"
        data-success="{{ $isEdit ? 'Code updated.' : 'Code created.' }}"
        novalidate
    >
        @csrf

        <div class="row g-3">
            <div class="col-lg-7">

                <div class="form-card">
                    <h2 class="form-card-title">The code</h2>
                    <p class="form-card-sub">This is what the customer sees on the offer card.</p>

                    <div class="field">
                        <label class="field-label" for="code">Code <span class="req">*</span></label>
                        <input type="text" class="field-input text-uppercase" id="code" name="code"
                               value="{{ $coupon->code }}" maxlength="40" required
                               placeholder="e.g. SAVE10" autocomplete="off"
                               aria-describedby="code-error">
                        <p class="field-error" id="code-error" data-error-for="code"></p>
                        <p class="field-hint">Letters, numbers, dashes and underscores. Stored upper-case.</p>
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="description">
                            Description <span class="field-opt">Optional</span>
                        </label>
                        <input type="text" class="field-input" id="description" name="description"
                               value="{{ $coupon->description }}" maxlength="160"
                               placeholder="e.g. 10% off your first order"
                               aria-describedby="description-error">
                        <p class="field-error" id="description-error" data-error-for="description"></p>
                        <p class="field-hint">
                            Left blank, the cart writes one from the rule &mdash; &ldquo;On orders over {{ $symbol }}99.00&rdquo;.
                        </p>
                    </div>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">The discount</h2>
                    <p class="form-card-sub">
                        A percentage comes off the order subtotal; a fixed amount comes off in dollars.
                    </p>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="field">
                                <label class="field-label" for="type">Type <span class="req">*</span></label>
                                <select class="field-select" id="type" name="type" required
                                        aria-describedby="type-error" data-coupon-type>
                                    <option value="percent" @selected($coupon->type === 'percent')>Percentage off</option>
                                    <option value="fixed" @selected($coupon->type === 'fixed')>Fixed amount off</option>
                                </select>
                                <p class="field-error" id="type-error" data-error-for="type"></p>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="field">
                                <label class="field-label" for="value">
                                    Value <span class="req">*</span>
                                    <span class="field-opt" data-value-unit data-symbol="{{ $symbol }}">{{ $coupon->type === 'fixed' ? $symbol : '%' }}</span>
                                </label>
                                <input type="number" class="field-input" id="value" name="value"
                                       value="{{ $coupon->value }}" step="0.01" min="0.01" required
                                       inputmode="decimal" placeholder="10"
                                       aria-describedby="value-error">
                                <p class="field-error" id="value-error" data-error-for="value"></p>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="field mb-0">
                                <label class="field-label" for="min_order_amount">
                                    Minimum order ({{ $symbol }})
                                </label>
                                <input type="number" class="field-input" id="min_order_amount" name="min_order_amount"
                                       value="{{ $coupon->min_order_amount ?? '0.00' }}" step="0.01" min="0"
                                       inputmode="decimal" placeholder="0.00"
                                       aria-describedby="min_order_amount-error">
                                <p class="field-error" id="min_order_amount-error" data-error-for="min_order_amount"></p>
                                <p class="field-hint">
                                    Spend this much and the code unlocks. The cart tells the customer how
                                    much more they need. <b>0</b> means any order.
                                </p>
                            </div>
                        </div>

                        <div class="col-sm-6" data-percent-only @if ($coupon->type === 'fixed') hidden @endif>
                            <div class="field mb-0">
                                <label class="field-label" for="max_discount_amount">
                                    Maximum discount ({{ $symbol }}) <span class="field-opt">Optional</span>
                                </label>
                                <input type="number" class="field-input" id="max_discount_amount" name="max_discount_amount"
                                       value="{{ $coupon->max_discount_amount }}" step="0.01" min="0.01"
                                       inputmode="decimal" placeholder="No cap"
                                       aria-describedby="max_discount_amount-error">
                                <p class="field-error" id="max_discount_amount-error" data-error-for="max_discount_amount"></p>
                                <p class="field-hint">Caps a percentage code on a large order.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">When it runs</h2>
                    <p class="form-card-sub">Leave both empty for a code that is always on.</p>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="field mb-0">
                                <label class="field-label" for="starts_at">
                                    Starts <span class="field-opt">Optional</span>
                                </label>
                                <input type="datetime-local" class="field-input" id="starts_at" name="starts_at"
                                       value="{{ $localDate($coupon->starts_at) }}"
                                       aria-describedby="starts_at-error">
                                <p class="field-error" id="starts_at-error" data-error-for="starts_at"></p>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="field mb-0">
                                <label class="field-label" for="ends_at">
                                    Ends <span class="field-opt">Optional</span>
                                </label>
                                <input type="datetime-local" class="field-input" id="ends_at" name="ends_at"
                                       value="{{ $localDate($coupon->ends_at) }}"
                                       aria-describedby="ends_at-error">
                                <p class="field-error" id="ends_at-error" data-error-for="ends_at"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="form-card">
                    <h2 class="form-card-title">Availability</h2>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Active</div>
                            <div class="sr-sub">Off disables the code everywhere at once</div>
                        </div>
                        <input type="checkbox" class="switch" name="is_active" value="1"
                               @checked($coupon->is_active) aria-label="Active">
                    </div>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Show on the storefront</div>
                            <div class="sr-sub">Listed on the cart and eligible for the shop promotion banner</div>
                        </div>
                        <input type="checkbox" class="switch" name="is_public" value="1"
                               @checked($coupon->is_public) aria-label="Show on the cart">
                    </div>

                    <div class="field mt-3">
                        <label class="field-label" for="usage_limit">
                            Total redemptions <span class="field-opt">Optional</span>
                        </label>
                        <input type="number" class="field-input" id="usage_limit" name="usage_limit"
                               value="{{ $coupon->usage_limit }}" min="1" step="1"
                               placeholder="Unlimited" aria-describedby="usage_limit-error">
                        <p class="field-error" id="usage_limit-error" data-error-for="usage_limit"></p>
                        <p class="field-hint">
                            The code stops working once it has been used this many times.
                            @if ($isEdit)
                                Used <b>{{ number_format($coupon->used_count) }}</b> so far.
                            @endif
                        </p>
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="sort_order">Sort order</label>
                        <input type="number" class="field-input" id="sort_order" name="sort_order"
                               value="{{ $coupon->sort_order ?? 0 }}" min="0" max="65535" step="1"
                               aria-describedby="sort_order-error">
                        <p class="field-error" id="sort_order-error" data-error-for="sort_order"></p>
                        <p class="field-hint">
                            Lower numbers are listed first. The first live public code is featured on the shop banner.
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('manager.coupons.index') }}" class="btn-ghost">Cancel</a>
                    <button type="submit" class="btn-gold">
                        <span class="spinner" aria-hidden="true"></span>
                        {{ $isEdit ? 'Save changes' : 'Create code' }}
                    </button>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
