{{--
    Create / edit a category. One form serves both: the endpoint and the button
    label are the only difference, and the API decides everything else.

    Nothing is validated as authoritative here - the browser hints (required,
    maxlength) are a convenience, and StoreProductCategoryRequest is what
    actually decides. Errors come back as JSON and are painted under each input.
--}}
@extends('manager.components.layout')

@section('title', $isEdit ? 'Edit category' : 'New category')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager/editor.css') }}">
@endpush

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $isEdit ? 'Edit category' : 'New category' }}</h1>
            <p class="ph-sub">
                {{ $isEdit
                    ? 'Changes go live on the storefront as soon as you save.'
                    : 'Group related products so customers can find them.' }}
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.categories.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to categories
            </a>
        </div>
    </div>

    <form
        data-api-form="{{ $isEdit
            ? route('api.manager.categories.update', $category)
            : route('api.manager.categories.store') }}"
        data-method="POST"
        data-redirect="{{ route('manager.categories.index') }}"
        data-success="{{ $isEdit ? 'Category updated.' : 'Category created.' }}"
        novalidate
    >
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="form-card">
                    <h2 class="form-card-title">Details</h2>
                    <p class="form-card-sub">The name is what customers see in the navigation.</p>

                    <div class="field">
                        <label class="field-label" for="name">Name <span class="req">*</span></label>
                        <input type="text" class="field-input" id="name" name="name"
                               value="{{ $category->name }}" maxlength="150"
                               placeholder="e.g. Sleep &amp; Recovery" required
                               aria-describedby="name-error">
                        <p class="field-error" id="name-error" data-error-for="name"></p>
                    </div>

                    {{-- Filled in as the name is typed, by the same rule the
                         server uses. An operator who edits it keeps what they
                         typed, and a blank one is still worked out on save. --}}
                    <div class="field">
                        <label class="field-label" for="slug">
                            URL <span class="field-opt">Optional</span>
                        </label>
                        <div class="slug-field">
                            <span class="slug-field__base">/shop?category=</span>
                            <input type="text" class="field-input" id="slug" name="slug"
                                   value="{{ $category->slug }}" maxlength="150"
                                   placeholder="worked-out-from-the-name"
                                   data-slug-from="name"
                                   aria-describedby="slug-error">
                        </div>
                        <p class="field-error" id="slug-error" data-error-for="slug"></p>
                        <p class="field-hint">
                            Letters, numbers and dashes.
                            @if ($isEdit)
                                <b>Changing this breaks every existing link to the category</b>
                                and any ranking it has built up.
                            @else
                                Left blank it is worked out from the name.
                            @endif
                        </p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="parent_id">Parent category</label>
                        <select class="field-select" id="parent_id" name="parent_id"
                                aria-describedby="parent_id-error">
                            <option value="">None &mdash; this is a top-level category</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>
                                    {{ $parent->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="field-error" id="parent_id-error" data-error-for="parent_id"></p>
                        <p class="field-hint">Leave empty unless this sits underneath another category.</p>
                    </div>

                    <div class="field mb-0">
                        <span class="field-label">Description</span>
                        <p class="form-card-sub">
                            Printed under the products when a customer browses this category
                            on the shop. Formatting is kept simple on purpose &mdash; the
                            sanitiser decides what survives into the database.
                        </p>

                        {{-- The editor writes into this; it is the field the
                             request and the sanitiser actually see. --}}
                        <input type="hidden" id="description" name="description"
                               value="{{ $category->description }}">

                        <div class="editor" data-editor data-editor-input="description">
                            <div class="ed-bar" data-editor-toolbar>
                                <button type="button" class="ed-btn" data-cmd="bold" title="Bold (Ctrl+B)" aria-label="Bold"><i class="bi bi-type-bold"></i></button>
                                <button type="button" class="ed-btn" data-cmd="italic" title="Italic (Ctrl+I)" aria-label="Italic"><i class="bi bi-type-italic"></i></button>
                                <button type="button" class="ed-btn" data-cmd="underline" title="Underline (Ctrl+U)" aria-label="Underline"><i class="bi bi-type-underline"></i></button>
                                <button type="button" class="ed-btn" data-cmd="strikeThrough" title="Strikethrough" aria-label="Strikethrough"><i class="bi bi-type-strikethrough"></i></button>

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
                                 aria-multiline="true" aria-label="Category description"
                                 data-placeholder="What belongs in this category…"></div>

                            <textarea class="ed-source" data-editor-source hidden
                                      spellcheck="false" aria-label="Category description HTML"></textarea>

                            <div class="ed-foot">
                                <span data-editor-count></span>
                                <span>Paragraph and headings, lists, quotes and links</span>
                            </div>
                        </div>

                        <p class="field-error mt-2" id="description-error" data-error-for="description"></p>
                    </div>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">Sections</h2>
                    <p class="form-card-sub">
                        Expandable sections on the category page, under the products &mdash;
                        how to choose between these, what the differences mean, anything a
                        product page cannot say on its own. Optional.
                    </p>

                    {{-- Deleting every row leaves no accordions[] keys in the post,
                         which would read as "leave them alone". This flag says the
                         form manages them, so an emptied repeater really does clear
                         them. --}}
                    <input type="hidden" name="accordions_present" value="1">

                    <div data-section-rows data-section-max="12">
                        @foreach ($category->accordions ?? [] as $index => $section)
                            @include('manager.partials.section-row', [
                                'name' => 'accordions',
                                'index' => $index,
                                'label' => $section['label'] ?? '',
                                'body' => $section['body'] ?? '',
                                'icon' => $section['icon'] ?? '',
                                'withIcon' => true,
                                'placeholder' => 'Heading, e.g. How to choose',
                            ])
                        @endforeach
                    </div>

                    {{-- One definition of a row, cloned by the repeater. __INDEX__ is
                         swapped for a unique one in the browser; it never reaches PHP. --}}
                    <template data-section-template>
                        @include('manager.partials.section-row', [
                            'name' => 'accordions',
                            'index' => '__INDEX__',
                            'label' => '',
                            'body' => '',
                            'icon' => '',
                            'withIcon' => true,
                            'placeholder' => 'Heading, e.g. How to choose',
                        ])
                    </template>

                    <button type="button" class="btn-ghost mt-2" data-section-add>
                        <i class="bi bi-plus-lg"></i> Add a section
                    </button>
                    <p class="field-error mt-2" data-error-for="accordions"></p>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">Search engine listing</h2>
                    <p class="form-card-sub">Optional. Falls back to the name and description when left empty.</p>

                    <div class="field">
                        <label class="field-label" for="meta_title">Meta title</label>
                        <input type="text" class="field-input" id="meta_title" name="meta_title"
                               value="{{ $category->meta_title }}" maxlength="180"
                               aria-describedby="meta_title-error">
                        <p class="field-error" id="meta_title-error" data-error-for="meta_title"></p>
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="meta_description">Meta description</label>
                        <textarea class="field-textarea" id="meta_description" name="meta_description"
                                  maxlength="255" rows="2"
                                  aria-describedby="meta_description-error">{{ $category->meta_description }}</textarea>
                        <p class="field-error" id="meta_description-error" data-error-for="meta_description"></p>
                        <p class="field-hint">Around 155 characters shows in full on a search results page.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="form-card">
                    <h2 class="form-card-title">Image</h2>
                    <p class="form-card-sub">Square works best. JPG, PNG or WebP, up to 2&nbsp;MB.</p>

                    <div data-image-picker>
                        <div class="image-preview" data-image-preview
                             @if (! $category->imageUrl()) hidden @endif>
                            @if ($category->imageUrl())
                                <img src="{{ $category->imageUrl() }}" alt="Current category image">
                                <button type="button" class="ip-remove" data-image-clear
                                        aria-label="Remove image">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            @endif
                        </div>

                        <div class="image-picker" data-image-drop tabindex="0" role="button"
                             aria-label="Choose a category image">
                            <div class="ip-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="ip-text"><b>Click to upload</b> or drag an image here</div>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                        </div>

                        {{-- Distinguishes "no new file" from "delete the stored one" --}}
                        <input type="hidden" name="remove_image" value="0" data-image-remove-flag>
                        <p class="field-error mt-2" data-error-for="image"></p>
                    </div>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">Visibility</h2>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Active</div>
                            <div class="sr-sub">Show this category on the storefront</div>
                        </div>
                        <input type="checkbox" class="switch" name="is_active" value="1"
                               @checked($category->is_active) aria-label="Active">
                    </div>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Featured</div>
                            <div class="sr-sub">Highlight it on the home page</div>
                        </div>
                        <input type="checkbox" class="switch" name="is_featured" value="1"
                               @checked($category->is_featured) aria-label="Featured">
                    </div>

                    <div class="field mt-3 mb-0">
                        <label class="field-label" for="sort_order">Sort order</label>
                        <input type="number" class="field-input" id="sort_order" name="sort_order"
                               value="{{ $category->sort_order ?? 0 }}" min="0" max="65535" step="1"
                               aria-describedby="sort_order-error">
                        <p class="field-error" id="sort_order-error" data-error-for="sort_order"></p>
                        <p class="field-hint">Lower numbers appear first.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('manager.categories.index') }}" class="btn-ghost">Cancel</a>
                    <button type="submit" class="btn-gold">
                        <span class="spinner" aria-hidden="true"></span>
                        {{ $isEdit ? 'Save changes' : 'Create category' }}
                    </button>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
    <script src="{{ asset('js/manager/editor.js') }}"></script>
@endpush
