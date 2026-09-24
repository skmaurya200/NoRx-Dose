{{--
    The brand lockup: the mark and the name.

    Shared by the header and the footer so the two cannot drift. An uploaded
    logo replaces the ✦ glyph; with none set, the design's own mark is drawn,
    which is why there is no placeholder image here.

    The name is split in two because the design sets the second word in gold.
    An operator who clears "Second word" gets the whole name in one colour,
    which is what a one-word brand needs.

    @var \App\Support\Settings\SiteSettings $site
--}}
@php
    $brand = $site->text('brand.name');
    $accent = $site->text('brand.name_accent');
    $logo = $site->image('brand.logo');

    // Only split on the accent when the name actually ends with it, or a
    // renamed brand would print its old second word.
    $lead = $accent !== '' && str_ends_with($brand, $accent)
        ? rtrim(mb_substr($brand, 0, mb_strlen($brand) - mb_strlen($accent)))
        : $brand;
@endphp

@if ($logo)
    <img src="{{ $logo }}" alt="{{ $brand }}" class="brand__logo">
@else
    <span class="brand__mark">&#10022;</span>{{ $lead }}@if ($lead !== $brand)&nbsp;<span>{{ $accent }}</span>@endif
@endif
