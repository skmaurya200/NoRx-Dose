{{--
    Shop grid card. Same markup shop.js used to build, with one omission noted
    below.

    @var \App\Models\Product $product
    @var int $index
--}}
<article class="card" style="--d:{{ ($index % 4) * 70 }}ms">
    <div class="card__media">
        @if ($product->is_featured)
            <span class="flag">✦ Top rated</span>
        @endif
        <button class="wish" aria-label="Save {{ $product->name }}" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.8 5.6a5 5 0 00-7.1 0L12 7.3l-1.7-1.7a5 5 0 10-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 000-7.1z"/></svg>
        </button>
        @include('partials.product-thumb')
    </div>
    <div class="card__body">
        <p class="card__eyebrow">{{ $product->category?->name }}</p>
        <h3 class="card__title">{{ $product->name }}</h3>
        <div class="stars"><b>{{ $product->ratingStars() }}</b> ({{ $product->rating_count }} {{ Str::plural('review', $product->rating_count) }})</div>

        {{--
            The mockup carried a "N people bought this in the last 24 hrs" line
            here. There is no orders table yet, so any number printed would be
            invented - and invented social proof on a live storefront is not a
            placeholder, it is a false claim to a customer. The line comes back
            the day the orders module can supply a real count.
        --}}

        <div class="stock">
            <div class="stock__row"><span>Stock remaining</span><b>{{ $product->stockBarLabel() }}</b></div>
            <div class="stock__bar"><span class="stock__fill" data-w="{{ $product->stockPercent() }}"></span></div>
        </div>
        <div class="price">
            <span class="price__now">{{ $product->priceFormatted() }}</span>
            @if ($product->compareAtFormatted())
                <span class="price__was">{{ $product->compareAtFormatted() }}</span>
            @endif
            @if ($product->discountPercent())
                <span class="price__save">SAVE {{ $product->discountPercent() }}%</span>
            @endif
        </div>
        <a class="card__btn" href="{{ route('product', $product->slug) }}">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M6 6L5 2H2"/></svg>
            Select options
        </a>
    </div>
</article>
