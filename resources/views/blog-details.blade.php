@extends('components.baselayout')

@section('title', ($post->meta_title ?: $post->title).' - NoRx Dose')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/blog-details.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="i-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
      <symbol id="i-back" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></symbol>
      <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></symbol>
      <symbol id="i-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.5.5l3-3A5 5 0 1013.5 3.5L12 5"/><path d="M14 11a5 5 0 00-7.5-.5l-3 3A5 5 0 1010.5 20.5L12 19"/></symbol>
    </svg>
@endsection

@section('content')
<article class="bpost">

  <!-- ---- header ---- -->
  <header class="bpost__head">
    <div class="shell">
      <nav class="crumb" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a><i>&rsaquo;</i>
        <a href="{{ route('blogs') }}">Journal</a><i>&rsaquo;</i>
        <span>{{ $post->categoryName() }}</span>
      </nav>

      <p class="post__meta">{{ $post->categoryName() }} &middot; {{ $post->readLabel() }}</p>
      <h1>{{ $post->title }}</h1>
      <p class="bpost__ex">{{ $post->excerptLabel(240) }}</p>

      <div class="bpost__by">
        <span class="avatar" aria-hidden="true">&#10022;</span>
        <span><b>{{ $post->author_name }}</b><span>{{ $post->publishedLabel() }}</span></span>
      </div>
    </div>
  </header>

  {{-- Every post gets a cover; without one of its own it uses the journal
       default, so the page keeps its shape. --}}
  <div class="shell">
    <div class="bpost__cover">
      <img src="{{ $post->coverUrl() ?? \App\Support\DefaultImage::article() }}" alt="{{ $post->coverAlt() }}"
           decoding="async" fetchpriority="high">
    </div>
  </div>

  <!-- ---- body ---- -->
  <div class="shell bpost__wrap">
    <div class="bpost__body">
      {{-- Printed unescaped, which is only safe because App\Support\HtmlSanitizer
           filtered it against an allow-list before it was ever stored - see
           App\Services\Blog\BlogService::preparePost(). --}}
      <div class="bpost__rich">{!! $post->body !!}</div>

      @if ($post->takeawayList() !== [])
        <div class="pull">
          <h2>The short version</h2>
          <ul class="takeaways">
            @foreach ($post->takeawayList() as $takeaway)
              <li><svg width="14" height="14"><use href="#i-check"/></svg>{{ $takeaway }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <p class="bpost__note">Written for general reading. It is not medical advice, and it is no substitute for talking to a professional who knows your history.</p>

      <div class="bpost__foot">
        <a href="{{ route('blogs') }}" class="btn-back"><svg width="14" height="14"><use href="#i-back"/></svg>All posts</a>
        <button class="btn-copy" id="copyLink"><svg width="14" height="14"><use href="#i-link"/></svg>Copy link</button>
      </div>
    </div>

    <!-- ---- sidebar ---- -->
    <aside class="bside">
      <div class="bside__card">
        <h2>About this post</h2>
        <dl class="facts">
          <div>
            <dt>Category</dt>
            <dd>
              @if ($post->category)
                <a href="{{ route('blogs', ['category' => $post->category->slug]) }}">{{ $post->category->name }}</a>
              @else
                Journal
              @endif
            </dd>
          </div>
          <div><dt>Reading time</dt><dd>{{ $post->readLabel() }}</dd></div>
          <div><dt>Published</dt><dd>{{ $post->publishedLabel() }}</dd></div>
          <div><dt>Written by</dt><dd>{{ $post->author_name }}</dd></div>
        </dl>
      </div>

      <div class="bside__card bside__card--dark">
        <h2>Start with the basics</h2>
        <p>The everyday range, batch tested and shipped the same day.</p>
        <a href="{{ route('shop') }}" class="btn-gold">Shop the range<svg width="14" height="14"><use href="#i-arrow"/></svg></a>
      </div>
    </aside>
  </div>

  <!-- ---- related ---- -->
  @if ($related->isNotEmpty())
  <section class="section related">
    <div class="shell">
      <div class="journal__head">
        <div>
          <p class="eyebrow">Keep reading</p>
          <h2>More from the journal</h2>
        </div>
        <a href="{{ route('blogs') }}" class="journal__link">Read all posts</a>
      </div>

      <div class="journal__grid">
        @foreach ($related as $item)
          @include('partials.post-card', ['post' => $item])
        @endforeach
      </div>
    </div>
  </section>
  @endif

</article>
@endsection

@push('scripts')
    <script src="{{ asset('js/pages/blog-details.js') }}"></script>
@endpush
