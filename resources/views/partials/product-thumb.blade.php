{{--
    A product's picture, for the three card layouts and anywhere else one is
    needed.

    Its own partial because all three cards drew the same thing and each had
    to branch on whether a photo existed. There is no branch now: a product
    without a photo gets the default bottle, marked so the CSS can letterbox
    it the way the illustration it replaced was drawn.

    @var \App\Models\Product $product
--}}
@php
    $photo = $product->thumbnailUrl();
@endphp

<div class="jar">
    <img src="{{ $photo ?? \App\Support\DefaultImage::product() }}"
         alt="{{ $product->name }}"
         @class(['jar__default' => ! $photo])
         loading="lazy">
</div>
