{{-- ==================== STICKY SHELL — ticker + nav travel together ==================== --}}
<div class="shell-top" id="shellTop">

{{-- ==================== TICKER ==================== --}}
<div class="ticker" aria-hidden="true">
    <div class="ticker__track" id="ticker">
        {{-- From Settings. Printed unescaped because each item is stored
             through HtmlSanitizer::inline(), which keeps <b> and <em> and
             nothing else. --}}
        <div class="ticker__group">
            @foreach (['one', 'two', 'three', 'four', 'five', 'six'] as $slot)
                @if ($site->has('ticker.'.$slot))
                    <span class="ticker__item">{!! $site->html('ticker.'.$slot) !!}</span>
                @endif
            @endforeach
        </div>
    </div>
</div>

{{-- ==================== NAV ==================== --}}
<header class="nav" id="nav">
    <div class="shell nav__in">
        <a href="{{ route('home') }}" class="brand">
            @include('components.brandmark')
        </a>

        <nav class="nav__links" id="menu">
            <a href="{{ route('home') }}" @class(['is-active' => request()->routeIs('home')])>Home</a>
            <a href="{{ route('shop') }}" @class(['is-active' => request()->routeIs('shop')])>Shop</a>
            <a href="{{ route('all-products') }}" @class(['is-active' => request()->routeIs('all-products')])>All products</a>
            <a href="{{ route('blogs') }}" @class(['is-active' => request()->routeIs('blogs', 'blog-details')])>Blogs</a>
            <a href="{{ route('reviews') }}" @class(['is-active' => request()->routeIs('reviews')])>Reviews</a>
            <a href="{{ route('shipping-policy') }}" @class(['is-active' => request()->routeIs('shipping-policy')])>Shipping</a>
            <a href="{{ route('faq') }}" @class(['is-active' => request()->routeIs('faq')])>FAQ</a>
            <a href="{{ route('about') }}" @class(['is-active' => request()->routeIs('about')])>About</a>
        </nav>

        <div class="nav__tools">
            {{-- A real form, so the box still works with JavaScript off: it
                 submits to /search and the results page does the rest. The
                 suggestion panel below is the enhancement on top of that. --}}
            {{-- POST, not GET: a GET form would write the term straight into
                 the address bar. It is posted, sealed and redirected, so the
                 URL only ever carries the sealed form. Still works with no
                 JavaScript - the suggestion panel below is the extra. --}}
            <form class="search" action="{{ route('search.submit') }}" method="POST" role="search"
                  id="searchBox" autocomplete="off">
                @csrf
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6E6A60" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="search" id="q" name="q" placeholder="Search products"
                       aria-label="Search products"
                       role="combobox" aria-expanded="false" aria-controls="searchPanel"
                       aria-autocomplete="list" maxlength="120"
                       value="{{ $searchTerm ?? '' }}">

                <div class="sugg" id="searchPanel" role="listbox"
                     aria-label="Search suggestions" hidden></div>
            </form>

            <button class="icon-btn search-toggle" id="mobileSearchToggle" type="button"
                    aria-label="Open product search" aria-controls="searchBox" aria-expanded="false">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1B1A16" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            </button>

            <a href="{{ route('cart') }}" class="icon-btn" aria-label="Open cart">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1B1A16" stroke-width="1.8"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M6 6L5 2H2"/></svg>
                <b id="cartN">0</b>
            </a>

            <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

</div>{{-- /.shell-top --}}
