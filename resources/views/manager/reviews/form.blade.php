{{--
    Write or edit a review. One form serves both; the endpoint and the button
    label are the only difference.

    A review created here is published immediately - there is nobody else to
    approve it. One edited here keeps whatever moderation state it already had,
    which is why there is no status control on this screen: the approve/reject
    switch lives on the list row, where the queue is.
--}}
@extends('manager.components.layout')

@section('title', $isEdit ? 'Edit review' : 'New review')

@section('content')

    <div class="page-head">
        <div>
            <h1 class="font-serif">{{ $isEdit ? 'Edit review' : 'Write a review' }}</h1>
            <p class="ph-sub">
                {{ $isEdit
                    ? 'Changes show on the storefront straight away if this review is approved.'
                    : 'A review you write here is published as soon as you save it.' }}
            </p>
        </div>
        <div class="ph-actions">
            <a href="{{ route('manager.reviews.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Back to reviews
            </a>
        </div>
    </div>

    <form
        data-api-form="{{ $isEdit
            ? route('api.manager.reviews.update', $review->id)
            : route('api.manager.reviews.store') }}"
        data-method="POST"
        data-redirect="{{ route('manager.reviews.index') }}"
        data-success="{{ $isEdit ? 'Review updated.' : 'Review published.' }}"
        novalidate
    >
        @csrf

        <div class="row g-3">
            <div class="col-lg-7">

                <div class="form-card">
                    <h2 class="form-card-title">The review</h2>
                    <p class="form-card-sub">This is what a visitor reads, word for word.</p>

                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="field">
                                <label class="field-label" for="rating">Rating <span class="req">*</span></label>
                                <select class="field-select" id="rating" name="rating" required
                                        aria-describedby="rating-error">
                                    @foreach ([5, 4, 3, 2, 1] as $star)
                                        <option value="{{ $star }}" @selected((int) $review->rating === $star)>
                                            {{ str_repeat('★', $star) }} &nbsp;{{ $star }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="field-error" id="rating-error" data-error-for="rating"></p>
                            </div>
                        </div>

                        <div class="col-sm-8">
                            <div class="field">
                                <label class="field-label" for="category">What it is about <span class="req">*</span></label>
                                <select class="field-select" id="category" name="category" required
                                        aria-describedby="category-error">
                                    @foreach (\App\Models\Review::CATEGORIES as $value => $label)
                                        <option value="{{ $value }}" @selected($review->category === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="field-error" id="category-error" data-error-for="category"></p>
                                <p class="field-hint">Sets which filter chip the review appears under.</p>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="title">
                            Headline <span class="field-opt">Optional</span>
                        </label>
                        <input type="text" class="field-input" id="title" name="title"
                               value="{{ $review->title }}" maxlength="150"
                               placeholder="e.g. Arrived next morning"
                               aria-describedby="title-error">
                        <p class="field-error" id="title-error" data-error-for="title"></p>
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="body">Review <span class="req">*</span></label>
                        <textarea class="field-input" id="body" name="body" rows="7" required
                                  maxlength="5000" placeholder="What was ordered, and how did it go?"
                                  aria-describedby="body-error">{{ $review->body }}</textarea>
                        <p class="field-error" id="body-error" data-error-for="body"></p>
                        <p class="field-hint">Two or three sentences reads best. Up to 5,000 characters.</p>
                    </div>
                </div>

                <div class="form-card">
                    <h2 class="form-card-title">Who wrote it</h2>
                    <p class="form-card-sub">
                        The name and the town are public. The email address never is &mdash; it is only
                        what ties the review to an order.
                    </p>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="field">
                                <label class="field-label" for="author_name">Name <span class="req">*</span></label>
                                <input type="text" class="field-input" id="author_name" name="author_name"
                                       value="{{ $review->author_name }}" maxlength="120" required
                                       placeholder="How it appears on the review"
                                       aria-describedby="author_name-error">
                                <p class="field-error" id="author_name-error" data-error-for="author_name"></p>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="field">
                                <label class="field-label" for="location">
                                    Town or city <span class="field-opt">Optional</span>
                                </label>
                                <input type="text" class="field-input" id="location" name="location"
                                       value="{{ $review->location }}" maxlength="80"
                                       placeholder="e.g. Denver" aria-describedby="location-error">
                                <p class="field-error" id="location-error" data-error-for="location"></p>
                            </div>
                        </div>
                    </div>

                    <div class="field mb-0">
                        <label class="field-label" for="author_email">
                            Email <span class="field-opt">Optional</span>
                        </label>
                        <input type="email" class="field-input" id="author_email" name="author_email"
                               value="{{ $review->author_email }}" maxlength="180"
                               placeholder="Never shown publicly" aria-describedby="author_email-error">
                        <p class="field-error" id="author_email-error" data-error-for="author_email"></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="form-card">
                    <h2 class="form-card-title">Placement</h2>

                    <div class="field">
                        <label class="field-label" for="product_id">Product</label>
                        <select class="field-select" id="product_id" name="product_id"
                                aria-describedby="product_id-error">
                            <option value="">About the shop, not a product</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((int) $review->product_id === $product->id)>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="field-error" id="product_id-error" data-error-for="product_id"></p>
                        <p class="field-hint">
                            Pick a product and the review shows on its page and counts towards its
                            rating. Leave it blank and it only appears on the reviews page.
                        </p>
                    </div>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Verified purchase</div>
                            <div class="sr-sub">Shows the verified badge on the review</div>
                        </div>
                        <input type="checkbox" class="switch" name="is_verified" value="1"
                               @checked($review->is_verified) aria-label="Verified purchase">
                    </div>

                    <div class="switch-row">
                        <div class="sr-text">
                            <div class="sr-title">Feature this review</div>
                            <div class="sr-sub">Pulled out at the top of the reviews page. One at a time</div>
                        </div>
                        <input type="checkbox" class="switch" name="is_featured" value="1"
                               @checked($review->is_featured) aria-label="Feature this review">
                    </div>
                </div>

                @if ($isEdit)
                    <div class="form-card">
                        <h2 class="form-card-title">Moderation</h2>
                        <dl class="kv kv--tight">
                            <div class="kv-row">
                                <dt>Status</dt>
                                <dd><span class="badge-status {{ $review->statusBadge() }}">{{ ucfirst($review->status) }}</span></dd>
                            </div>
                            <div class="kv-row">
                                <dt>Written by</dt>
                                <dd>{{ $review->source === 'manager' ? 'Staff' : 'Customer' }}</dd>
                            </div>
                            <div class="kv-row">
                                <dt>Received</dt>
                                <dd>{{ $review->created_at?->format('j M Y, H:i') }}</dd>
                            </div>
                            @if ($review->approved_at)
                                <div class="kv-row">
                                    <dt>Approved</dt>
                                    <dd>{{ $review->approved_at->format('j M Y, H:i') }}</dd>
                                </div>
                            @endif
                            <div class="kv-row">
                                <dt>Marked helpful</dt>
                                <dd>{{ number_format($review->helpful_count) }}</dd>
                            </div>
                        </dl>
                        <p class="field-hint mb-0">
                            Approve or reject from the list &mdash; that is where the queue is.
                        </p>
                    </div>
                @endif

                <div class="form-actions">
                    <a href="{{ route('manager.reviews.index') }}" class="btn-ghost">Cancel</a>
                    <button type="submit" class="btn-gold">
                        <span class="spinner" aria-hidden="true"></span>
                        {{ $isEdit ? 'Save changes' : 'Publish review' }}
                    </button>
                </div>
            </div>
        </div>
    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/manager/catalogue.js') }}"></script>
@endpush
