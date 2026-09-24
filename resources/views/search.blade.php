{{--
    Search results.

    Deliberately built on the shop's stylesheet and its product card: a search
    result is a product, and giving it a second look would make the same thing
    appear two ways on one site.

    noindex, because a results page has no content of its own and would
    compete in a search engine with the product pages it links to.
--}}
@extends('components.baselayout')

@section('title', $term !== '' ? 'Search: '.$term : 'Search')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages/shop.css') }}">
@endpush

@section('sprite')
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <symbol id="jar" viewBox="0 0 120 150">
        <rect x="44" y="6" width="32" height="16" rx="4" fill="#C9A227"/>
        <rect x="30" y="20" width="60" height="16" rx="5" fill="#B8912012"/>
        <rect x="26" y="30" width="68" height="106" rx="12" fill="#fff" stroke="#E7E2D6" stroke-width="2"/>
        <rect x="34" y="52" width="52" height="44" rx="6" fill="#F7EFD8"/>
        <rect x="42" y="64" width="36" height="4" rx="2" fill="#C9A227"/>
        <rect x="46" y="74" width="28" height="3" rx="1.5" fill="#D9CFAF"/>
        <rect x="50" y="82" width="20" height="3" rx="1.5" fill="#D9CFAF"/>
        <rect x="34" y="106" width="52" height="3" rx="1.5" fill="#EFEBE0"/>
        <rect x="34" y="114" width="38" height="3" rx="1.5" fill="#EFEBE0"/>
      </symbol>
    </svg>
@endsection

@section('content')

<div class="bar">
  <div class="shell bar__in">
    @if ($term === '')
      <p class="bar__count">Type something in the search box above to look for a product.</p>
    @else
      <p class="bar__count">
        <b>{{ $products->total() }}</b>
        {{ Str::plural('result', $products->total()) }} for &ldquo;{{ $term }}&rdquo;
      </p>
    @endif

    <div class="bar__right">
      <a href="{{ route('shop') }}" class="searchBack">Browse the whole shop &rarr;</a>
    </div>
  </div>
</div>

<main class="shop">
  <div class="shell">
    <div class="grid" id="grid">
      @forelse ($products as $product)
        @include('partials.product-card-shop', ['product' => $product, 'index' => $loop->index])
      @empty
        <div class="empty">
          @if ($term === '')
            <h3>Nothing searched for yet</h3>
            <p>Use the box in the header to find a product by name, brand or category.</p>
          @else
            <h3>Nothing matched &ldquo;{{ $term }}&rdquo;</h3>
            <p>Try a shorter word, or a brand or category name.</p>
          @endif

          {{-- What other people found something with. Only terms that returned
               results, so this never sends anyone to a second empty page. --}}
          @if ($popular)
            <div class="searchPopular">
              <span>Popular searches</span>
              @foreach ($popular as $suggestion)
                <a href="{{ \App\Support\SearchLink::url($suggestion) }}">{{ $suggestion }}</a>
              @endforeach
            </div>
          @endif
        </div>
      @endforelse
    </div>

    @if ($products->hasPages())
      {{ $products->onEachSide(1)->links('vendor.pagination.aurum') }}
    @endif
  </div>
</main>

@endsection

@push('scripts')
    <script src="{{ asset('js/pages/shop.js') }}"></script>
@endpush
