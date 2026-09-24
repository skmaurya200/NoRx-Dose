{{--
    Write / edit a journal post. One form serves both.

    The body is written in the editor in public/js/manager/editor.js, which
    keeps the hidden #body input in step. Nothing here is authoritative:
    StoreBlogPostRequest validates, and App\Support\HtmlSanitizer decides what
    markup actually survives into the database.
--}}
@extends('manager.components.layout')

@section('title', $isEdit ? 'Edit post' : 'New post')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager/editor.css') }}">
@endpush

@php
    $takeaways = $post->takeawayList();
    $localDate = fn ($value) => $value?->format('Y-m-d\TH:i');
@endphp

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $isEdit ? 'Edit post' : 'New post' }}</h1>
            <p class="ph-sub">
                @if ($isEdit)
                    Live at <code>/blogs/{{ $post->slug }}</code> once published &mdash;
                    renaming the post updates that address.
                @else
                    Drafts are invisible on the storefront until you publish them.
                @endif
            </p>
        </div>
        <div class="ph-actions">
            @if ($isEdit && $post->isPublished())
                <a href="{{ route('blog-details', $post->slug) }}" class="btn-ghost" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> View
                </a>
            @endif
            <a href="{{ route('manager.blog.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to posts
            </a>
        </div>
    </div>

    <form
        data-api-form="{{ $isEdit
            ? route('api.manager.blog.posts.update', $post)
            : route('api.manager.blog.posts.store') }}"
        data-method="POST"
        data-redirect="{{ route('manager.blog.index') }}"
        data-success="{{ $isEdit ? 'Post updated.' : 'Post created.' }}"
        novalidate
    >
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">

                <div class="form-card">
                    <div class="field">
                        <label class="field-label" for="title">Title <span class="req">*</span></label>
                        <input type="text" class="field-input" id="title" name="title"
                               value="{{ $post->title }}" maxlength="200" required
                               placeholder="How to read an ingredient list"
                               aria-describedby="title-error">
                        <p class="field-error" id="title-error" data-error-for="title"></p>
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="excerpt">
                            Excerpt <span class="field-opt">Optional</span>
                        </label>
                        <textarea class="field-textarea" id="excerpt" name="excerpt" rows="2"
                                  maxlength="400"
                                  placeholder="The line under the title on the card and in search results."
                                  aria-describedby="excerpt-error">{{ $post->excerpt }}</textarea>
                        <p class="field-error" id="excerpt-error" data-error-for="excerpt"></p>
                        <p class="field-hint">Left blank, the card uses the opening of the post.</p>
                    </div>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">The post</h2>
                    <p class="form-card-sub">
                        Formatting is kept simple on purpose &mdash; headings, lists, quotes and links
                        are what the storefront knows how to print. Pasted text arrives as plain text.
                    </p>

                    {{-- The editor writes into this; it is the field the
                         request and the sanitiser actually see. --}}
                    <input type="hidden" id="body" name="body" value="{{ $post->body }}">

                    <div class="editor" data-editor data-editor-input="body"
                         data-editor-upload="{{ route('api.manager.blog.uploads.store') }}">
                        <div class="ed-bar" data-editor-toolbar>
                            <button type="button" class="ed-btn" data-cmd="bold" title="Bold (Ctrl+B)" aria-label="Bold"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="ed-btn" data-cmd="italic" title="Italic (Ctrl+I)" aria-label="Italic"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="ed-btn" data-cmd="underline" title="Underline (Ctrl+U)" aria-label="Underline"><i class="bi bi-type-underline"></i></button>
                            <button type="button" class="ed-btn" data-cmd="strikeThrough" title="Strikethrough" aria-label="Strikethrough"><i class="bi bi-type-strikethrough"></i></button>

                            <span class="ed-sep" aria-hidden="true"></span>

                            {{-- One picker rather than seven buttons: paragraph and
                                 h1-h6 would fill half the toolbar, and a select also
                                 shows what the caret is currently sitting in. --}}
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
                            <button type="button" class="ed-btn" data-cmd="image" title="Upload an image" aria-label="Upload an image"><i class="bi bi-image"></i></button>
                            <button type="button" class="ed-btn" data-cmd="imageUrl" title="Image from an address" aria-label="Image from an address"><i class="bi bi-link"></i></button>
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
                             aria-multiline="true" aria-label="Post body"
                             data-placeholder="Start writing…"></div>

                        <textarea class="ed-source" data-editor-source hidden
                                  spellcheck="false" aria-label="Post body HTML"></textarea>

                        <div class="ed-foot">
                            <span data-editor-count></span>
                            <span>Paragraph and headings 1&ndash;6, lists, quotes, links and uploaded images</span>
                        </div>
                    </div>

                    <p class="field-error mt-2" data-error-for="body"></p>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">The short version</h2>
                    <p class="form-card-sub">
                        The bullet summary printed under the post. Leave it empty to drop the block.
                    </p>

                    {{-- No marker input is needed here: the request reads
                         takeaways with a default of [], so a form that posts
                         none is understood as "clear the list". --}}
                    <div data-take-rows data-take-max="{{ config('admin.blog.max_takeaways', 8) }}">
                        @foreach ($takeaways as $index => $line)
                            <div class="take-row">
                                <input type="text" class="field-input"
                                       name="takeaways[{{ $index }}]"
                                       value="{{ $line }}" maxlength="200"
                                       placeholder="One thing worth remembering"
                                       aria-label="Short-version line">
                                <button type="button" class="spec-remove" data-take-remove
                                        aria-label="Remove this line">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn-ghost mt-2" data-take-add>
                        <i class="bi bi-plus-lg"></i> Add a line
                    </button>
                    <p class="field-error mt-2" data-error-for="takeaways"></p>
                </div>

                <div class="form-card" data-seo
                     data-seo-base="{{ rtrim(url('/blogs'), '/') }}/"
                     data-seo-site="{{ config('seo.site_name') }}">
                    <h2 class="form-card-title">Search engine listing</h2>
                    <p class="form-card-sub">
                        How this post reads in a search result and in a shared link.
                        Everything here is optional &mdash; left blank it falls back to the
                        title and the excerpt.
                    </p>

                    {{-- A live approximation of the result, so the operator is
                         writing to the space they actually get rather than
                         guessing at character limits. --}}
                    <div class="snippet" data-seo-preview>
                        <span class="snippet__url" data-seo-url></span>
                        <span class="snippet__title" data-seo-title></span>
                        <span class="snippet__desc" data-seo-desc></span>
                    </div>

                    <div class="field">
                        <label class="field-label" for="slug">
                            URL <span class="field-opt">Optional</span>
                        </label>
                        <div class="slug-field">
                            <span class="slug-field__base">/blogs/</span>
                            <input type="text" class="field-input" id="slug" name="slug"
                                   value="{{ $post->slug }}" maxlength="200"
                                   placeholder="worked-out-from-the-title"
                                   aria-describedby="slug-error">
                        </div>
                        <p class="field-error" id="slug-error" data-error-for="slug"></p>
                        <p class="field-hint">
                            Letters, numbers and dashes.
                            @if ($isEdit)
                                <b>Changing this breaks every existing link to the post</b>
                                and any ranking it has built up.
                            @else
                                Left blank it is worked out from the title.
                            @endif
                        </p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="meta_title">
                            SEO title <span class="field-opt" data-count-for="meta_title"></span>
                        </label>
                        <input type="text" class="field-input" id="meta_title" name="meta_title"
                               value="{{ $post->meta_title }}" maxlength="200"
                               data-seo-input="title" data-count-max="60"
                               placeholder="Falls back to the post title"
                               aria-describedby="meta_title-error">
                        <p class="field-error" id="meta_title-error" data-error-for="meta_title"></p>
                        <p class="field-hint">Around 60 characters shows in full. Longer is trimmed with an ellipsis.</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="meta_description">
                            SEO description <span class="field-opt" data-count-for="meta_description"></span>
                        </label>
                        <textarea class="field-textarea" id="meta_description" name="meta_description"
                                  rows="2" maxlength="255" data-seo-input="desc" data-count-max="160"
                                  placeholder="Falls back to the excerpt."
                                  aria-describedby="meta_description-error">{{ $post->meta_description }}</textarea>
                        <p class="field-error" id="meta_description-error" data-error-for="meta_description"></p>
                        <p class="field-hint">Around 160 characters. Write it as a sentence a person would click.</p>
                    </div>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Hide from search engines</div>
                            <div class="sr-sub">
                                Stays live and linkable, but is marked noindex and left out of the sitemap
                            </div>
                        </div>
                        <input type="checkbox" class="switch" name="noindex" value="1"
                               @checked($isEdit && ! $post->is_indexable) aria-label="Hide from search engines">
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
                            @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label)
                                <option value="{{ $value }}" @selected($post->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="field-error" id="status-error" data-error-for="status"></p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="published_at">
                            Publish date <span class="field-opt">Optional</span>
                        </label>
                        <input type="datetime-local" class="field-input" id="published_at" name="published_at"
                               value="{{ $localDate($post->published_at) }}"
                               aria-describedby="published_at-error">
                        <p class="field-error" id="published_at-error" data-error-for="published_at"></p>
                        <p class="field-hint">
                            A future date schedules the post &mdash; it stays off the storefront,
                            including its own URL, until then. Left blank, publishing dates it now.
                        </p>
                    </div>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Featured</div>
                            <div class="sr-sub">The large post at the top of the journal</div>
                        </div>
                        <input type="checkbox" class="switch" name="is_featured" value="1"
                               @checked($post->is_featured) aria-label="Featured">
                    </div>
                    <p class="field-hint">There is one featured slot &mdash; setting this clears the last one.</p>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">Cover image</h2>
                    <p class="form-card-sub">Shown on the card and across the top of the post. Wide, up to 4&nbsp;MB.</p>

                    <div data-image-picker>
                        <div class="image-preview" data-image-preview
                             @if (! $post->coverUrl()) hidden @endif>
                            @if ($post->coverUrl())
                                <img src="{{ $post->coverUrl() }}" alt="Current cover image">
                                <button type="button" class="ip-remove" data-image-clear
                                        aria-label="Remove image">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            @endif
                        </div>

                        <div class="image-picker" data-image-drop tabindex="0" role="button"
                             aria-label="Choose the cover image">
                            <div class="ip-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <div class="ip-text"><b>Click to upload</b> or drag an image here</div>
                            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        </div>

                        <input type="hidden" name="remove_cover" value="0" data-image-remove-flag>
                        <p class="field-error mt-2" data-error-for="cover"></p>
                    </div>

                    <div class="field mt-3 mb-0">
                        <label class="field-label" for="cover_alt">
                            Describe the image <span class="field-opt">Optional</span>
                        </label>
                        <input type="text" class="field-input" id="cover_alt" name="cover_alt"
                               value="{{ $post->cover_alt }}" maxlength="200"
                               placeholder="e.g. A row of amber bottles on a linen cloth"
                               aria-describedby="cover_alt-error">
                        <p class="field-error" id="cover_alt-error" data-error-for="cover_alt"></p>
                        <p class="field-hint">
                            Read aloud by screen readers and used by image search. Left blank
                            it falls back to the post title, which is better than nothing but
                            not by much.
                        </p>
                    </div>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">Details</h2>

                    <div class="field">
                        <label class="field-label" for="category_id">Category</label>
                        <select class="field-select" id="category_id" name="category_id"
                                aria-describedby="category_id-error">
                            <option value="">None</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($post->category_id === $category->id)>
                                    {{ $category->name }}@unless ($category->is_active) (hidden)@endunless
                                </option>
                            @endforeach
                        </select>
                        <p class="field-error" id="category_id-error" data-error-for="category_id"></p>
                        <p class="field-hint">
                            <a href="{{ route('manager.blog.categories.index') }}">Manage categories</a>
                        </p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="author_name">Author</label>
                        <input type="text" class="field-input" id="author_name" name="author_name"
                               value="{{ $post->author_name }}" maxlength="120"
                               placeholder="The Aurum team" aria-describedby="author_name-error">
                        <p class="field-error" id="author_name-error" data-error-for="author_name"></p>
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="read_minutes">
                            Reading time <span class="field-opt">Optional</span>
                        </label>
                        <input type="number" class="field-input" id="read_minutes" name="read_minutes"
                               value="{{ $post->read_minutes ?: '' }}" min="1" max="999" step="1"
                               placeholder="Worked out from the post"
                               aria-describedby="read_minutes-error">
                        <p class="field-error" id="read_minutes-error" data-error-for="read_minutes"></p>
                        <p class="field-hint">Left blank, it is counted from the body at 200 words a minute.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('manager.blog.index') }}" class="btn-ghost">Cancel</a>
                    <button type="submit" class="btn-gold">
                        <span class="spinner" aria-hidden="true"></span>
                        {{ $isEdit ? 'Save changes' : 'Create post' }}
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
