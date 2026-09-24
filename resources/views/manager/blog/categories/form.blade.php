{{--
    Create / edit a journal category. One form serves both.
--}}
@extends('manager.components.layout')

@section('title', $isEdit ? 'Edit category' : 'New category')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $isEdit ? 'Edit category' : 'New journal category' }}</h1>
            <p class="ph-sub">
                {{ $isEdit
                    ? 'Renaming it updates the filter link on the storefront.'
                    : 'Group posts so readers can find the ones they came for.' }}
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.blog.categories.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to categories
            </a>
        </div>
    </div>

    <form
        data-api-form="{{ $isEdit
            ? route('api.manager.blog.categories.update', $category)
            : route('api.manager.blog.categories.store') }}"
        data-method="POST"
        data-redirect="{{ route('manager.blog.categories.index') }}"
        data-success="{{ $isEdit ? 'Category updated.' : 'Category created.' }}"
        novalidate
    >
        @csrf

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="form-card">
                    <h2 class="form-card-title">Details</h2>

                    <div class="field">
                        <label class="field-label" for="name">Name <span class="req">*</span></label>
                        <input type="text" class="field-input" id="name" name="name"
                               value="{{ $category->name }}" maxlength="120" required
                               placeholder="e.g. Ingredients" aria-describedby="name-error">
                        <p class="field-error" id="name-error" data-error-for="name"></p>
                        @if ($isEdit)
                            <p class="field-hint">
                                Filter link: <code>/blogs?category={{ $category->slug }}</code>
                            </p>
                        @endif
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="description">
                            Description <span class="field-opt">Optional</span>
                        </label>
                        <textarea class="field-textarea" id="description" name="description"
                                  rows="3" maxlength="500"
                                  placeholder="A line about what belongs in here. For your reference only."
                                  aria-describedby="description-error">{{ $category->description }}</textarea>
                        <p class="field-error" id="description-error" data-error-for="description"></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="form-card">
                    <h2 class="form-card-title">Visibility</h2>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Active</div>
                            <div class="sr-sub">Off removes the chip; the posts stay published</div>
                        </div>
                        <input type="checkbox" class="switch" name="is_active" value="1"
                               @checked($category->is_active) aria-label="Active">
                    </div>

                    <div class="field mt-3 mb-0">
                        <label class="field-label" for="sort_order">Sort order</label>
                        <input type="number" class="field-input" id="sort_order" name="sort_order"
                               value="{{ $category->sort_order ?? 0 }}" min="0" max="65535" step="1"
                               aria-describedby="sort_order-error">
                        <p class="field-error" id="sort_order-error" data-error-for="sort_order"></p>
                        <p class="field-hint">Lower numbers appear first in the chip row.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('manager.blog.categories.index') }}" class="btn-ghost">Cancel</a>
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
@endpush
