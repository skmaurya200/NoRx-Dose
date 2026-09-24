{{--
    One journal card. Shared by the home page rail, the listing grid and the
    "more from the journal" block, so a post looks the same wherever it turns
    up and there is one place to change it.

    Expects: $post (App\Models\BlogPost). Optional: $delay (ms, for the
    staggered reveal) and $reveal (whether the card animates in at all).
--}}
@php
    $url = route('blog-details', $post->slug);

    // Every post shows a picture. Without a cover of its own it gets the
    // journal default, so the grid never has a hole in it.
    $fallback = \App\Support\DefaultImage::article();
@endphp

<article class="post"
         @if ($reveal ?? false) data-reveal @endif
         @if (! empty($delay)) style="--d:{{ $delay }}ms" @endif>
    <a class="post__media" href="{{ $url }}" tabindex="-1" aria-hidden="true">
        <img src="{{ $post->coverUrl() ?? $fallback }}" alt="{{ $post->coverAlt() }}"
             loading="lazy" decoding="async">
    </a>
    <div class="post__body">
        <p class="post__meta">{{ $post->categoryName() }} &middot; {{ $post->readLabel() }}</p>
        <h3><a href="{{ $url }}">{{ $post->title }}</a></h3>
        <p>{{ $post->excerptLabel() }}</p>
        <p class="byline">{{ $post->publishedLabel() }}</p>
        <a href="{{ $url }}" class="post__more">Read more</a>
    </div>
</article>
