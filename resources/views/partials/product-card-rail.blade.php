{{--
    Home page rail card. Used by both "Customer favourites" and "New arrivals".

    The markup is a straight lift of the original static card - same elements,
    same classes, same order. Only the values are now real.

    @var \App\Models\Product $product
--}}
@php $badge = $product->badge(); @endphp

<article class="card">
    <div class="card__media">
        @if ($badge)
            <span @class(['card__flag', 'card__flag--gold' => $badge['gold']])>{{ $badge['label'] }}</span>
        @endif

        @include('partials.product-thumb')
    </div>
    <div class="card__body">
        <h3 class="card__title">{{ $product->name }}</h3>
        <div class="stars"><b>{{ $product->ratingStars() }}</b> ({{ number_format((float) $product->rating_avg, 1) }})</div>
        <div class="card__price">{{ $product->priceFormatted() }}</div>
        <a class="card__btn" href="{{ route('product', $product->slug) }}">Select options</a>
    </div>
</article>
