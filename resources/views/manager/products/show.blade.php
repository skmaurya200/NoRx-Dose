{{--
    Product details. Everything about one product on a single screen, plus the
    gallery manager - uploading, setting the primary image and removing one all
    post to the product-detail API without leaving the page.
--}}
@extends('manager.components.layout')

@section('title', $product->name)

@php
    $detail = $product->detail;
    $specifications = $detail?->specifications ?? [];
    $maxGallery = (int) config('admin.catalogue.max_gallery_images');

    // The bar is relative to twice the low-stock threshold, so "comfortable"
    // reads as full and the bar starts moving well before the product runs out.
    $stockCeiling = max(($product->low_stock_threshold ?: 5) * 2, 1);
    $stockPercent = $product->track_inventory
        ? min(100, (int) round(($product->stock_quantity / $stockCeiling) * 100))
        : 100;
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $product->name }}</h1>
            <p class="ph-sub">
                SKU {{ $product->sku }}
                @if ($product->category)
                    &middot; {{ $product->category->name }}
                @endif
                &middot;
                <span class="badge-status badge-{{ $product->status }}">{{ ucfirst($product->status) }}</span>
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.products.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="{{ route('manager.products.edit', $product) }}" class="btn-gold">
                <i class="bi bi-pencil"></i> Edit product
            </a>
        </div>
    </div>

    <div class="detail-grid">

        {{-- ==================================================== LEFT COLUMN --}}
        <div>
            <div class="panel mb-3">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Gallery</h2>
                        <div class="panel-sub">
                            {{ $product->images->count() }} of {{ $maxGallery }} images used
                        </div>
                    </div>
                    <button type="button" class="btn-ghost" data-gallery-trigger>
                        <span class="spinner" aria-hidden="true"></span>
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </div>

                {{-- The endpoint lives on the container; the buttons inside carry
                     their own per-image endpoints. --}}
                <div data-gallery="{{ route('api.manager.products.detail.images.store', $product) }}">
                    @if ($product->images->isEmpty())
                        <div class="empty-state py-4">
                            <div class="es-icon"><i class="bi bi-images"></i></div>
                            <h3>No gallery images</h3>
                            <p>Upload up to {{ $maxGallery }} photos. The first one becomes the primary image.</p>
                        </div>
                    @else
                        <div class="gallery-grid">
                            @foreach ($product->images as $image)
                                <div class="gallery-item {{ $image->is_primary ? 'is-primary' : '' }}">
                                    <img src="{{ $image->url() }}"
                                         alt="{{ $image->alt_text ?: $product->name }}" loading="lazy">

                                    @if ($image->is_primary)
                                        <span class="gi-badge">Primary</span>
                                    @endif

                                    <div class="gi-actions">
                                        @unless ($image->is_primary)
                                            <button type="button" class="gi-btn"
                                                    data-image-primary="{{ route('api.manager.products.detail.images.primary', [$product, $image]) }}"
                                                    title="Make primary" aria-label="Make this the primary image">
                                                <i class="bi bi-star"></i>
                                            </button>
                                        @endunless

                                        <button type="button" class="gi-btn gi-btn--danger"
                                                data-image-delete="{{ route('api.manager.products.detail.images.destroy', [$product, $image]) }}"
                                                title="Remove" aria-label="Remove this image">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <input type="file" id="galleryUpload" data-gallery-input multiple hidden
                       accept="image/jpeg,image/png,image/webp">
            </div>

            @if ($product->short_description || $product->description)
                <div class="panel mb-3">
                    <div class="panel-head">
                        <h2 class="panel-title">Description</h2>
                    </div>

                    @if ($product->short_description)
                        <p class="prose-block fw-semibold">{{ $product->short_description }}</p>
                    @endif

                    @if ($product->description)
                        {{-- nl2br over an escaped string: the copy is plain text
                             typed by an operator, so it must not render as HTML. --}}
                        <div class="prose-block">{!! nl2br(e($product->description)) !!}</div>
                    @endif
                </div>
            @endif

            @php
                $detailBlocks = collect([
                    'Ingredients' => $detail?->ingredients,
                    'Benefits' => $detail?->benefits,
                    'How to use' => $detail?->how_to_use,
                    'Storage' => $detail?->storage,
                    'Warnings' => $detail?->warnings,
                ])->filter();
            @endphp

            @if ($detailBlocks->isNotEmpty())
                <div class="panel mb-3">
                    <div class="panel-head">
                        <h2 class="panel-title">Product detail</h2>
                    </div>

                    @foreach ($detailBlocks as $label => $body)
                        <div class="mb-3">
                            <div class="field-label">{{ $label }}</div>
                            <div class="prose-block">{!! nl2br(e($body)) !!}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (! empty($specifications))
                <div class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title">Specifications</h2>
                    </div>

                    <dl class="kv-list">
                        @foreach ($specifications as $spec)
                            <div class="kv-row">
                                <dt>{{ $spec['label'] ?? '' }}</dt>
                                <dd>{{ $spec['value'] ?? '' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>

        {{-- =================================================== RIGHT COLUMN --}}
        <div>
            <div class="panel mb-3">
                <div class="panel-head">
                    <h2 class="panel-title">Pricing</h2>
                </div>

                <div class="price-display">
                    <span class="pd-now">{{ $product->currency }} {{ number_format((float) $product->price, 2) }}</span>
                    @if ($product->discountPercent())
                        <span class="pd-was">{{ number_format((float) $product->compare_at_price, 2) }}</span>
                        <span class="pd-off">{{ $product->discountPercent() }}% off</span>
                    @endif
                </div>

                <dl class="kv-list mt-3">
                    @if ($product->cost_price)
                        <div class="kv-row">
                            <dt>Cost price</dt>
                            <dd>{{ $product->currency }} {{ number_format((float) $product->cost_price, 2) }}</dd>
                        </div>
                        <div class="kv-row">
                            <dt>Margin</dt>
                            <dd>
                                {{-- Back-office only; this figure never reaches the storefront resource. --}}
                                {{ number_format((float) $product->price - (float) $product->cost_price, 2) }}
                                ({{ (float) $product->price > 0
                                    ? round(((float) $product->price - (float) $product->cost_price) / (float) $product->price * 100)
                                    : 0 }}%)
                            </dd>
                        </div>
                    @endif

                    @if ($product->unit)
                        <div class="kv-row"><dt>Pack size</dt><dd>{{ $product->unit }}</dd></div>
                    @endif

                    @if ($product->weight_grams)
                        <div class="kv-row"><dt>Weight</dt><dd>{{ $product->weight_grams }} g</dd></div>
                    @endif
                </dl>
            </div>

            <div class="panel mb-3">
                <div class="panel-head">
                    <h2 class="panel-title">Inventory</h2>
                    <span class="badge-status
                        @if (! $product->track_inventory) badge-muted
                        @elseif ($product->isOutOfStock()) badge-danger
                        @elseif ($product->isLowStock()) badge-warn
                        @else badge-active @endif">
                        {{ $product->stockLabel() }}
                    </span>
                </div>

                @if ($product->track_inventory)
                    <div class="d-flex justify-content-between align-items-baseline">
                        <span class="stat-value font-serif">{{ number_format($product->stock_quantity) }}</span>
                        <span class="cell-meta">low at {{ $product->low_stock_threshold }}</span>
                    </div>

                    <div class="stock-meter
                        @if ($product->isOutOfStock()) is-out
                        @elseif ($product->isLowStock()) is-low @endif">
                        <span style="width: {{ $stockPercent }}%"></span>
                    </div>

                    @if ($product->allow_backorder)
                        <p class="field-hint mt-2">
                            <i class="bi bi-info-circle"></i>
                            Backorders are allowed, so this stays purchasable at zero stock.
                        </p>
                    @endif
                @else
                    <p class="field-hint mb-0">
                        Inventory is not tracked for this product, so it never shows as out of stock.
                    </p>
                @endif
            </div>

            <div class="panel mb-3">
                <div class="panel-head">
                    <h2 class="panel-title">Organisation</h2>
                </div>

                <dl class="kv-list">
                    <div class="kv-row">
                        <dt>Category</dt>
                        <dd>{{ $product->category?->name ?? '—' }}</dd>
                    </div>
                    <div class="kv-row"><dt>Brand</dt><dd>{{ $product->brand ?: '—' }}</dd></div>
                    <div class="kv-row"><dt>SKU</dt><dd>{{ $product->sku }}</dd></div>
                    <div class="kv-row"><dt>URL</dt><dd>/{{ $product->slug }}</dd></div>
                    <div class="kv-row">
                        <dt>Featured</dt>
                        <dd>{{ $product->is_featured ? 'Yes' : 'No' }}</dd>
                    </div>

                    @if ($detail?->manufacturer)
                        <div class="kv-row"><dt>Manufacturer</dt><dd>{{ $detail->manufacturer }}</dd></div>
                    @endif
                    @if ($detail?->country_of_origin)
                        <div class="kv-row"><dt>Origin</dt><dd>{{ $detail->country_of_origin }}</dd></div>
                    @endif
                    @if ($detail?->shelf_life_months)
                        <div class="kv-row"><dt>Shelf life</dt><dd>{{ $detail->shelf_life_months }} months</dd></div>
                    @endif
                    @if ($detail?->is_vegetarian || $detail?->is_gluten_free)
                        <div class="kv-row">
                            <dt>Dietary</dt>
                            <dd>
                                @if ($detail->is_vegetarian)
                                    <span class="badge-status badge-active">Vegetarian</span>
                                @endif
                                @if ($detail->is_gluten_free)
                                    <span class="badge-status badge-active">Gluten free</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="panel mb-3">
                <div class="panel-head">
                    <h2 class="panel-title">Reviews</h2>
                </div>

                @if ($product->rating_count > 0)
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="stat-value font-serif">{{ number_format((float) $product->rating_avg, 1) }}</span>
                        <span class="stars">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="bi {{ $i <= round((float) $product->rating_avg) ? 'bi-star-fill' : 'bi-star' }}"></i>
                            @endfor
                        </span>
                    </div>
                    <div class="cell-meta">from {{ number_format($product->rating_count) }} reviews</div>
                @else
                    <p class="field-hint mb-0">No reviews yet.</p>
                @endif
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">History</h2>
                </div>

                <dl class="kv-list">
                    <div class="kv-row">
                        <dt>Created</dt>
                        <dd>{{ $product->created_at?->format('j M Y, g:i a') }}</dd>
                    </div>
                    <div class="kv-row">
                        <dt>Last updated</dt>
                        <dd>{{ $product->updated_at?->format('j M Y, g:i a') }}</dd>
                    </div>
                    <div class="kv-row">
                        <dt>Published</dt>
                        <dd>{{ $product->published_at?->format('j M Y') ?? 'Not published' }}</dd>
                    </div>
                </dl>

                <button type="button" class="btn-danger-soft w-100 mt-3"
                        data-delete="{{ route('api.manager.products.destroy', $product) }}"
                        data-redirect="{{ route('manager.products.index') }}"
                        data-confirm-title="Delete this product?"
                        data-confirm-message="{{ $product->name }} will be removed from the storefront. Past orders keep their record of it.">
                    <i class="bi bi-trash"></i> Delete product
                </button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
