<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Title, description, canonical, robots, Open Graph, Twitter and the
         JSON-LD graph. Driven by the App\Support\PageSeo the controller built,
         with defaults for the pages that build none. --}}
    @include('components.seo')

    {{-- The browser-tab icon, when one has been uploaded in Settings. With
         none set no tag is emitted at all, and the browser falls back to
         whatever favicon.ico is in public/ - which is better than pointing at
         a file that is not there. --}}
    @if ($site->image('brand.favicon'))
        <link rel="icon" href="{{ $site->image('brand.favicon') }}">
        <link rel="apple-touch-icon" href="{{ $site->image('brand.favicon') }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- shared shell: tokens, base, ticker, header, footer --}}
    <link rel="stylesheet" href="{{ asset('css/common.css') }}">
    @if (config('chat.enabled'))
        <link rel="stylesheet" href="{{ asset('css/chat.css') }}">
    @endif

    {{-- per-page styles --}}
    @stack('styles')
</head>
<body>

{{-- page level svg sprite, declared before anything that <use>s it --}}
@yield('sprite')

@include('components.header')

@yield('content')

@include('components.footer')

@include('components.chat-widget')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- shared behaviour: marquee, sticky nav, mobile menu --}}
<script src="{{ asset('js/common.js') }}"></script>

{{-- Catalogue-level settings the cart needs, declared server-side so the
     browser can never quote a shipping price the order would not be charged.
     The endpoints are named routes rather than literals for the same reason. --}}
@php
    // Built here rather than inline in @json(...): the directive's argument
    // parser trips over the nested array literals below.
    $shopConfig = [
        'currency_symbol' => config('shop.currency_symbol', '$'),
        'shipping' => config('shop.shipping', []),
        'free_shipping_over' => config('shop.free_shipping_over'),
        'free_shipping_method' => config('shop.free_shipping_method'),
        'max_line_quantity' => config('shop.max_line_quantity', 99),
        // What a cart line shows when it has no picture of its own - a line
        // saved before images were carried, for instance.
        'default_image' => \App\Support\DefaultImage::product(),
        'endpoints' => [
            'coupons' => route('api.storefront.coupons.index'),
            'apply_coupon' => route('api.storefront.coupons.apply'),
            'quote' => route('api.storefront.checkout.quote'),
            'place_order' => route('api.storefront.checkout.place'),
            'submit_review' => route('api.storefront.reviews.submit'),
            // __ID__ is swapped for the review's id by the page script - the
            // route needs a number to generate, and the id is not known here.
            'reviews_helpful' => route('api.storefront.reviews.helpful', ['review' => '__ID__']),
            'search' => route('api.storefront.search.suggest'),
        ],
        'urls' => [
            'cart' => route('cart'),
            'checkout' => route('checkout'),
            'shop' => route('shop'),
        ],
    ];
@endphp
<script>window.AURUM_SHOP = @json($shopConfig);</script>

{{-- the header search box: a real form on its own, this adds suggestions --}}
<script src="{{ asset('js/search.js') }}"></script>

{{-- one cart for the whole site, kept in localStorage --}}
<script src="{{ asset('js/cart-store.js') }}"></script>
@if (config('chat.enabled'))
    <script src="{{ asset('js/chat.js') }}" defer></script>
@endif

{{-- per-page scripts --}}
@stack('scripts')

</body>
</html>
