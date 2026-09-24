{{--
    All-products grid card. Identical markup to what all-products.js used to
    build - the stagger delay, the wishlist button and the jar illustration are
    all unchanged.

    data-cat carries the category name so the chip filter can work against the
    rendered DOM instead of a JavaScript array. It is an attribute only, and
    changes nothing on screen.

    @var \App\Models\Product $product
    @var int $index
--}}
<article class="card" data-cat="{{ $product->category?->name }}" style="--d:{{ ($index % 8) * 45 }}ms">
    <div class="card__media">
        <button class="wish" aria-label="Save {{ $product->name }}" aria-pressed="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.8 5.6a5 5 0 00-7.1 0L12 7.3l-1.7-1.7a5 5 0 10-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 000-7.1z"/></svg>
        </button>
        @include('partials.product-thumb')
    </div>
    <div class="card__body">
        <h3 class="card__title">{{ $product->name }}</h3>
        <div class="stars"><b>{{ $product->ratingStars() }}</b> ({{ $product->rating_count }})</div>
        <div class="price">
            <span class="price__now">{{ $product->priceFormatted() }}</span>
            {{-- Only printed when there is a real previous price to strike through. --}}
            @if ($product->compareAtFormatted())
                <span class="price__was">{{ $product->compareAtFormatted() }}</span>
            @endif
        </div>
        <a class="card__btn" href="{{ route('product', $product->slug) }}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M6 6L5 2H2"/></svg>
            Select options
        </a>
    </div>
</article>
