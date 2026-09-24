{{--
    Every tag a page tells a search engine or a share preview about itself.

    Rendered once from the layout's <head>. A controller that built a
    App\Support\PageSeo gets its values; one that did not still gets a
    canonical URL, a description and the site-wide schema, because the tag a
    page forgets is the one that costs it.
--}}
@php
    /** @var \App\Support\PageSeo $seo */
    $seo = $seo ?? \App\Support\PageSeo::make();

    // Falls back to whatever @section('title') the page set, so the pages that
    // predate this partial keep the titles they always had.
    $title = $seo->resolvedTitle(trim($__env->yieldContent('title')) ?: null);

    $description = $seo->resolvedDescription();
    $canonical = $seo->resolvedCanonical();
    $image = $seo->resolvedImage();
    $brand = \App\Support\Settings\Site::name();
    $twitter = \App\Support\Settings\Site::twitterHandle();
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">

{{-- One address per page. Without it a page reachable at two URLs - with a
     tracking parameter, say - is two competing pages. --}}
<link rel="canonical" href="{{ $canonical }}">

@if ($seo->noindex)
    {{-- Thin or duplicated by nature: a search results page has no content of
         its own and would compete with the pages it links to. Still followed,
         so the links on it are worth something. --}}
    <meta name="robots" content="noindex, follow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
@endif

@if ($seo->prev)
    <link rel="prev" href="{{ $seo->prev }}">
@endif
@if ($seo->next)
    <link rel="next" href="{{ $seo->next }}">
@endif

{{-- Open Graph: what Facebook, LinkedIn, WhatsApp and Slack read --}}
<meta property="og:site_name" content="{{ $brand }}">
<meta property="og:locale" content="{{ config('seo.locale') }}">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">

@if ($image)
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:alt" content="{{ $seo->imageAlt ?: $title }}">
@endif

@if ($seo->type === 'article')
    @if ($seo->publishedAt)
        <meta property="article:published_time" content="{{ $seo->publishedAt }}">
    @endif
    @if ($seo->modifiedAt)
        <meta property="article:modified_time" content="{{ $seo->modifiedAt }}">
    @endif
    @if ($seo->author)
        <meta property="article:author" content="{{ $seo->author }}">
    @endif
    @if ($seo->section)
        <meta property="article:section" content="{{ $seo->section }}">
    @endif
@endif

{{-- Twitter/X reads its own namespace, and falls back to Open Graph for the
     rest, so only what differs is repeated here. --}}
<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
@if ($twitter)
    <meta name="twitter:site" content="{{ $twitter }}">
@endif

{{-- Structured data. One <script> per page carrying a @graph, rather than
     several competing blocks. --}}
<script type="application/ld+json">{!! json_encode($seo->graph(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

<link rel="alternate" type="application/rss+xml"
      title="{{ $brand }} journal" href="{{ route('blogs.feed') }}">
