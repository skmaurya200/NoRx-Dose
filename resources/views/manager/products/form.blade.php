{{--
    Create / edit a product. One form, five tabs, one submit.

    The tabs are panes of a single <form>, not separate forms - so a value typed
    on "Details" is still in the payload when the operator saves from "Media",
    and a validation error on a hidden pane flags its tab (see markTabsWithErrors
    in api.js) instead of failing silently.
--}}
@extends('manager.components.layout')

@section('title', $isEdit ? 'Edit product' : 'New product')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager/editor.css') }}">
@endpush

@php
    $detail = $product->detail;
    $specifications = $detail?->specifications ?? [];
    $extraSections = $detail?->extra_sections ?? [];
    $maxGallery = (int) config('admin.catalogue.max_gallery_images');
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $isEdit ? 'Edit product' : 'New product' }}</h1>
            <p class="ph-sub">
                @if ($isEdit)
                    SKU {{ $product->sku }} &middot; last updated {{ $product->updated_at?->diffForHumans() }}
                @else
                    Everything a customer needs to decide. You can save it as a draft first.
                @endif
            </p>
        </div>
        <div class="ph-actions">
            @if ($isEdit)
                <a href="{{ route('manager.products.show', $product) }}" class="btn-ghost">
                    <i class="bi bi-eye"></i> View details
                </a>
            @endif
            <a href="{{ route('manager.products.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to products
            </a>
        </div>
    </div>

    <form
        data-api-form="{{ $isEdit
            ? route('api.manager.products.update', $product)
            : route('api.manager.products.store') }}"
        data-method="POST"
        data-redirect="{{ $isEdit
            ? route('manager.products.show', $product)
            : route('manager.products.index') }}"
        data-success="{{ $isEdit ? 'Product updated.' : 'Product created.' }}"
        novalidate
    >
        @csrf

        <div class="form-tabs" role="tablist" aria-label="Product sections">
            @foreach ([
                'tab-general' => ['General', 'bi-card-text'],
                'tab-pricing' => ['Pricing &amp; stock', 'bi-tag'],
                'tab-details' => ['Details', 'bi-list-ul'],
                'tab-media' => ['Media', 'bi-images'],
                'tab-seo' => ['Search listing', 'bi-search'],
            ] as $id => [$label, $icon])
                <button type="button" role="tab"
                        @class(['form-tab', 'active' => $loop->first])
                        data-tab-target="{{ $id }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="{{ $id }}">
                    <i class="bi {{ $icon }}"></i> {!! $label !!}
                    <span class="tab-dot" aria-hidden="true"></span>
                </button>
            @endforeach
        </div>

        {{-- ============================================================ GENERAL --}}
        <div class="tab-pane-custom active" id="tab-general" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="form-card">
                        <h2 class="form-card-title">Basics</h2>
                        <p class="form-card-sub">What the product is called and where it sits.</p>

                        <div class="field">
                            <label class="field-label" for="name">Product name <span class="req">*</span></label>
                            <input type="text" class="field-input" id="name" name="name"
                                   value="{{ $product->name }}" maxlength="180"
                                   placeholder="e.g. Calm Magnesium Complex" required
                                   aria-describedby="name-error">
                            <p class="field-error" id="name-error" data-error-for="name"></p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="field">
                                    <label class="field-label" for="category_id">Category <span class="req">*</span></label>
                                    <select class="field-select" id="category_id" name="category_id" required
                                            aria-describedby="category_id-error">
                                        <option value="">Choose a category…</option>
                                        @foreach ($categories as $option)
                                            <option value="{{ $option['id'] }}"
                                                    @selected($product->category_id === $option['id'])>
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="field-error" id="category_id-error" data-error-for="category_id"></p>
                                    @if ($categories->isEmpty())
                                        <p class="field-hint">
                                            No categories yet &mdash;
                                            <a href="{{ route('manager.categories.create') }}" class="link-gold">create one first</a>.
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="field">
                                    <label class="field-label" for="brand">Brand</label>
                                    <input type="text" class="field-input" id="brand" name="brand"
                                           value="{{ $product->brand }}" maxlength="120"
                                           placeholder="NoRx Dose" aria-describedby="brand-error">
                                    <p class="field-error" id="brand-error" data-error-for="brand"></p>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label class="field-label" for="short_description">Short description</label>
                            <textarea class="field-textarea" id="short_description" name="short_description"
                                      maxlength="400" rows="2"
                                      placeholder="One or two lines shown on the product card."
                                      aria-describedby="short_description-error">{{ $product->short_description }}</textarea>
                            <p class="field-error" id="short_description-error" data-error-for="short_description"></p>
                        </div>

                        <div class="field mb-0">
                            <span class="field-label">Full description</span>
                            <p class="field-hint mb-2">
                                Printed as its own section on the product page. Headings, lists and
                                links survive; anything else is stripped when it is saved.
                            </p>

                            {{-- The editor writes into this; it is the field the
                                 request and the sanitiser actually see. --}}
                            <input type="hidden" id="description" name="description"
                                   value="{{ $product->description }}">

                            <div class="editor" data-editor data-editor-input="description">
                                <div class="ed-bar" data-editor-toolbar>
                                    <button type="button" class="ed-btn" data-cmd="bold" title="Bold (Ctrl+B)" aria-label="Bold"><i class="bi bi-type-bold"></i></button>
                                    <button type="button" class="ed-btn" data-cmd="italic" title="Italic (Ctrl+I)" aria-label="Italic"><i class="bi bi-type-italic"></i></button>
                                    <button type="button" class="ed-btn" data-cmd="underline" title="Underline (Ctrl+U)" aria-label="Underline"><i class="bi bi-type-underline"></i></button>

                                    <span class="ed-sep" aria-hidden="true"></span>

                                    <select class="ed-select" data-editor-block
                                            title="Paragraph or heading" aria-label="Paragraph or heading">
                                        <option value="p">Paragraph</option>
                                        <option value="h1">Heading 1</option>
                                        <option value="h2">Heading 2</option>
                                        <option value="h3">Heading 3</option>
                                        <option value="h4">Heading 4</option>
                                        <option value="h5">Heading 5</option>
                                        <option value="h6">Heading 6</option>
                                    </select>
                                    <button type="button" class="ed-btn" data-cmd="block" data-value="blockquote" title="Quote" aria-label="Quote"><i class="bi bi-quote"></i></button>

                                    <span class="ed-sep" aria-hidden="true"></span>

                                    <button type="button" class="ed-btn" data-cmd="insertUnorderedList" title="Bullet list" aria-label="Bullet list"><i class="bi bi-list-ul"></i></button>
                                    <button type="button" class="ed-btn" data-cmd="insertOrderedList" title="Numbered list" aria-label="Numbered list"><i class="bi bi-list-ol"></i></button>

                                    <span class="ed-sep" aria-hidden="true"></span>

                                    <button type="button" class="ed-btn" data-cmd="link" title="Link (Ctrl+K)" aria-label="Add link"><i class="bi bi-link-45deg"></i></button>
                                    <button type="button" class="ed-btn" data-cmd="hr" title="Divider" aria-label="Divider"><i class="bi bi-dash-lg"></i></button>

                                    <span class="ed-sep" aria-hidden="true"></span>

                                    <button type="button" class="ed-btn" data-cmd="clear" title="Clear formatting" aria-label="Clear formatting"><i class="bi bi-eraser"></i></button>
                                    <button type="button" class="ed-btn" data-cmd="undo" title="Undo" aria-label="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <button type="button" class="ed-btn" data-cmd="redo" title="Redo" aria-label="Redo"><i class="bi bi-arrow-clockwise"></i></button>

                                    <span class="ed-spacer"></span>

                                    <button type="button" class="ed-btn" data-editor-source-toggle
                                            title="Edit the HTML directly" aria-pressed="false" aria-label="HTML source">
                                        <i class="bi bi-code-slash"></i>
                                    </button>
                                </div>

                                <div class="ed-area" data-editor-area contenteditable="true" role="textbox"
                                     aria-multiline="true" aria-label="Full description"
                                     data-placeholder="What this product is, who it is for, what is in it…"></div>

                                <textarea class="ed-source" data-editor-source hidden
                                          spellcheck="false" aria-label="Full description HTML"></textarea>

                                <div class="ed-foot">
                                    <span data-editor-count></span>
                                    <span>Paragraph and headings, lists, quotes and links</span>
                                </div>
                            </div>

                            <p class="field-error mt-2" id="description-error" data-error-for="description"></p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="form-card">
                        <h2 class="form-card-title">Publishing</h2>

                        <div class="field">
                            <label class="field-label" for="status">Status <span class="req">*</span></label>
                            <select class="field-select" id="status" name="status" required
                                    aria-describedby="status-error">
                                @foreach (\App\Models\Product::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($product->status === $status)>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="field-error" id="status-error" data-error-for="status"></p>
                            <p class="field-hint">
                                Only <b>active</b> products appear on the storefront.
                            </p>
                        </div>

                        <div class="switch-row">
                            <div class="sr-text">
                                <div class="sr-title">Featured</div>
                                <div class="sr-sub">Show on the home page</div>
                            </div>
                            <input type="checkbox" class="switch" name="is_featured" value="1"
                                   @checked($product->is_featured) aria-label="Featured">
                        </div>

                        <div class="field mt-3 mb-0">
                            <label class="field-label" for="sku">SKU</label>
                            <input type="text" class="field-input" id="sku" name="sku"
                                   value="{{ $product->sku }}" maxlength="60"
                                   placeholder="Generated if left blank" aria-describedby="sku-error">
                            <p class="field-error" id="sku-error" data-error-for="sku"></p>
                            <p class="field-hint">Letters, numbers, dots, dashes and underscores.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================================================== PRICING & STOCK --}}
        <div class="tab-pane-custom" id="tab-pricing" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="form-card">
                        <h2 class="form-card-title">Pack sizes</h2>
                        <p class="form-card-sub">
                            The &ldquo;Select size&rdquo; chooser on the product page, and where this product
                            is priced. The <b>first size</b> is the one selected by default, and its price is
                            what listing cards and search results show.
                        </p>

                        <div class="pack-head" aria-hidden="true">
                            <span>Size</span>
                            <span>Price</span>
                            <span>Compare at</span>
                            <span>Stock</span>
                            <span>Best</span>
                            <span></span>
                        </div>

                        {{-- Deleting the last row leaves no packs[] keys in the post, which
                             would read as "leave the sizes alone". This flag says the form
                             manages them, so an emptied repeater really does clear them. --}}
                        <input type="hidden" name="packs_present" value="1">

                        <div data-pack-rows data-pack-max="12"
                             data-currency="{{ $product->currency ?: 'USD' }}">
                            @foreach ($product->packs as $index => $pack)
                                <div class="pack-row">
                                    <input type="text" class="field-input pack-label"
                                           name="packs[{{ $index }}][label]"
                                           value="{{ $pack->label }}" maxlength="60"
                                           placeholder="e.g. 30 count" aria-label="Size label">
                                    <input type="number" class="field-input"
                                           name="packs[{{ $index }}][price]"
                                           value="{{ $pack->price }}" step="0.01" min="0"
                                           inputmode="decimal" placeholder="Price" aria-label="Price">
                                    <input type="number" class="field-input"
                                           name="packs[{{ $index }}][compare_at_price]"
                                           value="{{ $pack->compare_at_price }}" step="0.01" min="0"
                                           inputmode="decimal" placeholder="Compare at" aria-label="Compare-at price">
                                    <input type="number" class="field-input"
                                           name="packs[{{ $index }}][stock_quantity]"
                                           value="{{ $pack->stock_quantity }}" step="1" min="0"
                                           inputmode="numeric" placeholder="Stock" aria-label="Stock">
                                    <span class="pack-best">
                                        <input type="checkbox" class="switch" name="packs[{{ $index }}][is_best_value]"
                                               value="1" @checked($pack->is_best_value)
                                               aria-label="Mark as best value">
                                    </span>
                                    <button type="button" class="spec-remove" data-pack-remove
                                            aria-label="Remove this size">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="btn-ghost mt-2" data-pack-add>
                            <i class="bi bi-plus-lg"></i> Add size
                        </button>
                        <p class="field-error mt-2" data-error-for="packs"></p>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="form-card">
                        <h2 class="form-card-title">Inventory</h2>
                        <p class="form-card-sub">Turn tracking off for anything you never run out of.</p>

                        <div class="switch-row">
                            <div class="sr-text">
                                <div class="sr-title">Track inventory</div>
                                <div class="sr-sub">Count stock down as orders come in</div>
                            </div>
                            <input type="checkbox" class="switch" name="track_inventory" value="1"
                                   @checked($product->track_inventory) aria-label="Track inventory">
                        </div>

                        <div class="switch-row">
                            <div class="sr-text">
                                <div class="sr-title">Allow backorders</div>
                                <div class="sr-sub">Keep selling at zero stock</div>
                            </div>
                            <input type="checkbox" class="switch" name="allow_backorder" value="1"
                                   @checked($product->allow_backorder) aria-label="Allow backorders">
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-sm-6">
                                <div class="field mb-0">
                                    <label class="field-label" for="stock_quantity">Stock on hand</label>
                                    <input type="number" class="field-input" id="stock_quantity" name="stock_quantity"
                                           value="{{ $product->stock_quantity ?? 0 }}" min="0" step="1"
                                           inputmode="numeric" aria-describedby="stock_quantity-error">
                                    <p class="field-error" id="stock_quantity-error" data-error-for="stock_quantity"></p>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="field mb-0">
                                    <label class="field-label" for="low_stock_threshold">Low stock at</label>
                                    <input type="number" class="field-input" id="low_stock_threshold"
                                           name="low_stock_threshold" value="{{ $product->low_stock_threshold ?? 5 }}"
                                           min="0" step="1" inputmode="numeric"
                                           aria-describedby="low_stock_threshold-error">
                                    <p class="field-error" id="low_stock_threshold-error" data-error-for="low_stock_threshold"></p>
                                    <p class="field-hint">Flags the product in the list below this number.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================ DETAILS --}}
        <div class="tab-pane-custom" id="tab-details" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="form-card">
                        <h2 class="form-card-title">Product detail</h2>
                        <p class="form-card-sub">
                            The three every product needs. Anything else this one happens to need
                            goes in the section underneath.
                        </p>

                        @foreach ([
                            'benefits' => ['Benefits', 'What it helps with.'],
                            'how_to_use' => ['How to use', 'Dosage, timing and anything to take it with.'],
                            'warnings' => ['Warnings', 'Allergens, interactions, and who should not take this.'],
                        ] as $field => [$label, $hint])
                            <div class="field @if ($loop->last) mb-0 @endif">
                                <label class="field-label" for="{{ $field }}">{{ $label }}</label>
                                <textarea class="field-textarea" id="{{ $field }}" name="{{ $field }}"
                                          rows="3" maxlength="5000" placeholder="{{ $hint }}"
                                          aria-describedby="{{ $field }}-error">{{ $detail?->{$field} }}</textarea>
                                <p class="field-error" id="{{ $field }}-error" data-error-for="{{ $field }}"></p>
                                @if ($field === 'warnings')
                                    <p class="field-hint">
                                        Anything a customer must read before buying belongs here, not in the description.
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="form-card">
                        <h2 class="form-card-title">Specifications</h2>
                        <p class="form-card-sub">Free-form rows shown as a table on the product page.</p>

                        <div data-spec-rows data-spec-max="30">
                            @foreach ($specifications as $index => $spec)
                                <div class="spec-row">
                                    <input type="text" class="field-input spec-label"
                                           name="specifications[{{ $index }}][label]"
                                           value="{{ $spec['label'] ?? '' }}" maxlength="80"
                                           placeholder="Label, e.g. Serving size">
                                    <input type="text" class="field-input"
                                           name="specifications[{{ $index }}][value]"
                                           value="{{ $spec['value'] ?? '' }}" maxlength="255"
                                           placeholder="Value, e.g. 2 capsules daily">
                                    <button type="button" class="spec-remove" data-spec-remove
                                            aria-label="Remove this row">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="btn-ghost mt-2" data-spec-add>
                            <i class="bi bi-plus-lg"></i> Add row
                        </button>
                        <p class="field-error mt-2" data-error-for="specifications"></p>
                    </div>
                </div>
            </div>

            {{-- Everything past the three above is opt-in. Ingredients and storage
                 keep their own columns because the storefront and the chat index
                 read them by name; anything else this product needs is a row the
                 operator writes themselves. --}}
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-card mb-0">
                        <h2 class="form-card-title">More detail</h2>
                        <p class="form-card-sub">
                            All optional. Add only what this particular product needs &mdash;
                            each one becomes its own section on the product page.
                        </p>

                        <div class="row g-3">
                            @foreach ([
                                'ingredients' => ['Ingredients', 'One per line, or a comma-separated list.', 5000],
                                'storage' => ['Storage', 'e.g. Keep in a cool, dry place away from sunlight.', 2000],
                            ] as $field => [$label, $hint, $limit])
                                <div class="col-lg-6">
                                    <div class="field">
                                        <label class="field-label" for="{{ $field }}">
                                            {{ $label }} <span class="field-opt">Optional</span>
                                        </label>
                                        <textarea class="field-textarea" id="{{ $field }}" name="{{ $field }}"
                                                  rows="4" maxlength="{{ $limit }}" placeholder="{{ $hint }}"
                                                  aria-describedby="{{ $field }}-error">{{ $detail?->{$field} }}</textarea>
                                        <p class="field-error" id="{{ $field }}-error" data-error-for="{{ $field }}"></p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- These lived in the Manufacturing card. They are not
                             manufacturing facts, they are badges the product page
                             prints, so removing that card moved them here rather
                             than losing them. --}}
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="switch-row">
                                    <div class="sr-text">
                                        <div class="sr-title">Vegetarian</div>
                                        <div class="sr-sub">Show the vegetarian badge</div>
                                    </div>
                                    <input type="checkbox" class="switch" name="is_vegetarian" value="1"
                                           @checked($detail?->is_vegetarian) aria-label="Vegetarian">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="switch-row">
                                    <div class="sr-text">
                                        <div class="sr-title">Gluten free</div>
                                        <div class="sr-sub">Show the gluten-free badge</div>
                                    </div>
                                    <input type="checkbox" class="switch" name="is_gluten_free" value="1"
                                           @checked($detail?->is_gluten_free) aria-label="Gluten free">
                                </div>
                            </div>
                        </div>

                        <div class="field mb-0 mt-3">
                            <label class="field-label">
                                Your own sections <span class="field-opt">Optional</span>
                            </label>
                            <p class="field-hint mb-2">
                                A heading and what goes under it &mdash; "What's in the box",
                                "Dilution guide", whatever this product actually needs.
                            </p>

                            {{-- Deleting every row leaves no extra_sections[] keys in the
                                 post, which would read as "leave them alone". This flag
                                 says the form manages them, so an emptied repeater really
                                 does clear them. --}}
                            <input type="hidden" name="extra_sections_present" value="1">

                            <div data-section-rows data-section-max="12">
                                @foreach ($extraSections as $index => $section)
                                    @include('manager.partials.section-row', [
                                        'name' => 'extra_sections',
                                        'index' => $index,
                                        'label' => $section['label'] ?? '',
                                        'body' => $section['body'] ?? '',
                                    ])
                                @endforeach
                            </div>

                            {{-- One definition of a row, cloned by the repeater. __INDEX__
                                 is swapped for a unique one in the browser; it never
                                 reaches PHP. --}}
                            <template data-section-template>
                                @include('manager.partials.section-row', [
                                    'name' => 'extra_sections',
                                    'index' => '__INDEX__',
                                    'label' => '',
                                    'body' => '',
                                ])
                            </template>

                            <button type="button" class="btn-ghost mt-2" data-section-add>
                                <i class="bi bi-plus-lg"></i> Add a section
                            </button>
                            <p class="field-error mt-2" data-error-for="extra_sections"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================== MEDIA --}}
        <div class="tab-pane-custom" id="tab-media" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="form-card">
                        <h2 class="form-card-title">Main image</h2>
                        <p class="form-card-sub">The photo on the product card. Square, up to 4&nbsp;MB.</p>

                        <div data-image-picker>
                            <div class="image-preview" data-image-preview
                                 @if (! $product->thumbnailUrl()) hidden @endif>
                                @if ($product->thumbnailUrl())
                                    <img src="{{ $product->thumbnailUrl() }}" alt="Current main image">
                                    <button type="button" class="ip-remove" data-image-clear
                                            aria-label="Remove image">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                @endif
                            </div>

                            <div class="image-picker" data-image-drop tabindex="0" role="button"
                                 aria-label="Choose the main product image">
                                <div class="ip-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                <div class="ip-text"><b>Click to upload</b> or drag an image here</div>
                                <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp">
                            </div>

                            <input type="hidden" name="remove_thumbnail" value="0" data-image-remove-flag>
                            <p class="field-error mt-2" data-error-for="thumbnail"></p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="form-card">
                        <h2 class="form-card-title">Gallery</h2>
                        <p class="form-card-sub">
                            Up to {{ $maxGallery }} extra photos.
                            @if ($isEdit)
                                Manage the ones already uploaded from the
                                <a href="{{ route('manager.products.show', $product) }}" class="link-gold">details screen</a>.
                            @endif
                        </p>

                        @if ($isEdit && $product->images->isNotEmpty())
                            <div class="gallery-grid mb-3">
                                @foreach ($product->images as $image)
                                    <div class="gallery-item {{ $image->is_primary ? 'is-primary' : '' }}">
                                        <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" loading="lazy">
                                        @if ($image->is_primary)
                                            <span class="gi-badge">Primary</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="image-picker" data-gallery-drop>
                            <div class="ip-icon"><i class="bi bi-images"></i></div>
                            <div class="ip-text">
                                <b>Choose files</b> to add to the gallery
                            </div>
                            <input type="file" name="gallery[]" multiple id="galleryInput"
                                   accept="image/jpeg,image/png,image/webp"
                                   style="display:block; margin:12px auto 0; font-size:12px;">
                        </div>
                        <p class="field-error mt-2" data-error-for="gallery"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================ SEO --}}
        <div class="tab-pane-custom" id="tab-seo" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="form-card">
                        <h2 class="form-card-title">Search engine listing</h2>
                        <p class="form-card-sub">Optional. Falls back to the product name and short description.</p>

                        <div class="field">
                            <label class="field-label" for="meta_title">Meta title</label>
                            <input type="text" class="field-input" id="meta_title" name="meta_title"
                                   value="{{ $product->meta_title }}" maxlength="180"
                                   aria-describedby="meta_title-error">
                            <p class="field-error" id="meta_title-error" data-error-for="meta_title"></p>
                        </div>

                        <div class="field">
                            <label class="field-label" for="meta_description">Meta description</label>
                            <textarea class="field-textarea" id="meta_description" name="meta_description"
                                      rows="3" maxlength="255"
                                      aria-describedby="meta_description-error">{{ $product->meta_description }}</textarea>
                            <p class="field-error" id="meta_description-error" data-error-for="meta_description"></p>
                            <p class="field-hint">Around 155 characters shows in full on a search results page.</p>
                        </div>

                        <div class="field mb-0">
                            <label class="field-label" for="published_at">Publish date</label>
                            <input type="datetime-local" class="field-input" id="published_at" name="published_at"
                                   value="{{ $product->published_at?->format('Y-m-d\TH:i') }}"
                                   aria-describedby="published_at-error">
                            <p class="field-error" id="published_at-error" data-error-for="published_at"></p>
                            <p class="field-hint">
                                Leave empty to publish as soon as the status is set to active.
                            </p>
                        </div>
                    </div>
                </div>

                @if ($isEdit)
                    <div class="col-lg-5">
                        <div class="form-card">
                            <h2 class="form-card-title">Storefront URL</h2>
                            <p class="form-card-sub">Derived from the name and kept unique automatically.</p>
                            <input type="text" class="field-input" value="/{{ $product->slug }}" readonly
                                   aria-label="Storefront URL">
                            <p class="field-hint">Renaming the product changes this address.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('manager.products.index') }}" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-gold">
                <span class="spinner" aria-hidden="true"></span>
                {{ $isEdit ? 'Save changes' : 'Create product' }}
            </button>
        </div>
    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
    <script src="{{ asset('js/manager/editor.js') }}"></script>
@endpush
