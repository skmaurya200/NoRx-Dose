@extends('components.baselayout')

@section('title', 'Journal - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/blogs.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="i-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
      <symbol id="i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
      <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></symbol>
    </svg>
@endsection

@section('content')
<!-- ==================== HERO ==================== -->
<section class="bhero">
  <div class="shell">
    <p class="eyebrow eyebrow--center">{{ $content->text('hero.eyebrow') }}</p>
    <h1>{!! $content->html('hero.title') !!}</h1>
    <p class="bhero__sub">{{ $content->text('hero.subtitle') }}</p>
  </div>
</section>

<main class="shell blogs">

  <!-- ---- filters ----
       Links and a GET form rather than JavaScript filtering: a filtered view
       is then a real, shareable URL, and the page works the same whether the
       journal has six posts or six hundred. -->
  <form class="bfilter" method="GET" action="{{ route('blogs') }}" id="blogFilter">
    <div class="chips" role="list" aria-label="Filter posts by category">
      <a href="{{ route('blogs', ['q' => $filters['q'] ?: null]) }}"
         @class(['chip', 'is-on' => $filters['category'] === ''])
         role="listitem"
         @if ($filters['category'] === '') aria-current="true" @endif>All posts</a>

      @foreach ($categories as $category)
        <a href="{{ route('blogs', ['category' => $category->slug, 'q' => $filters['q'] ?: null]) }}"
           @class(['chip', 'is-on' => $filters['category'] === $category->slug])
           role="listitem"
           @if ($filters['category'] === $category->slug) aria-current="true" @endif>
          {{ $category->name }}
          <span class="chip__n">{{ $category->published_posts_count }}</span>
        </a>
      @endforeach
    </div>

    {{-- Carried through so searching does not silently drop the category --}}
    <input type="hidden" name="category" value="{{ $filters['category'] }}">

    <label class="bsearch">
      <svg width="13" height="13"><use href="#i-search"/></svg>
      <input type="search" id="q" name="q" value="{{ $filters['q'] }}"
             placeholder="Search the journal" aria-label="Search the journal">
    </label>
  </form>

  @if ($isFiltered)
    <p class="bresult">
      {{ $posts->total() }} {{ Str::plural('post', $posts->total()) }}
      @if ($filters['q'] !== '') matching &ldquo;{{ $filters['q'] }}&rdquo; @endif
      @if ($filters['category'] !== '')
        in {{ $categories->firstWhere('slug', $filters['category'])?->name ?? $filters['category'] }}
      @endif
      &middot; <a href="{{ route('blogs') }}">clear</a>
    </p>
  @endif

  <!-- ---- listing ----
       On phones the featured post and regular posts share one two-column
       grid. On larger screens the featured post keeps its lead treatment. -->
  @if ($featured || $posts->isNotEmpty())
    <div class="journal__listing">
      @if ($featured)
        <article class="feat">
          <a class="feat__media" href="{{ route('blog-details', $featured->slug) }}" tabindex="-1" aria-hidden="true">
            <img src="{{ $featured->coverUrl() ?? \App\Support\DefaultImage::article() }}" alt="{{ $featured->coverAlt() }}"
                 decoding="async" fetchpriority="high">
          </a>
          <div class="feat__body">
            <p class="post__meta">{{ $featured->categoryName() }} &middot; {{ $featured->readLabel() }}</p>
            <h2><a href="{{ route('blog-details', $featured->slug) }}">{{ $featured->title }}</a></h2>
            <p class="feat__ex">{{ $featured->excerptLabel(220) }}</p>
            <p class="byline">{{ $featured->author_name }} &middot; {{ $featured->publishedLabel() }}</p>
            <a href="{{ route('blog-details', $featured->slug) }}" class="btn-gold">
              Read the post<svg width="14" height="14"><use href="#i-arrow"/></svg>
            </a>
          </div>
        </article>
      @endif

      @if ($posts->isNotEmpty())
        <div class="journal__grid" id="grid">
          @foreach ($posts as $i => $post)
            @include('partials.post-card', ['post' => $post, 'delay' => $i * 60])
          @endforeach
        </div>
      @endif
    </div>

    @if ($posts->hasPages())
      <nav class="bpages" aria-label="Journal pages">
        {{ $posts->onEachSide(1)->links('pagination::bootstrap-5') }}
      </nav>
    @endif
  @else
    <p class="bempty">
      @if ($isFiltered)
        Nothing here matches that. Try another category or search term.
      @else
        The first post is on its way.
      @endif
    </p>
  @endif

  <!-- ---- newsletter ---- -->
  <section class="bnews">
    <div>
      <h2>New posts, now and then</h2>
      <p>One email when something worth reading goes up. Never more than twice a month.</p>
    </div>
    <form class="bnews__form" id="newsForm">
      <input type="email" required placeholder="Your email address" aria-label="Email address">
      <button type="submit">Subscribe</button>
    </form>
    <p class="bnews__ok" id="newsOk" role="status"></p>
  </section>

</main>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/blogs.js') }}"></script>
@endpush
