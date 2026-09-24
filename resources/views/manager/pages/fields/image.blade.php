{{--
    An uploaded picture.

    rawImage, not image: the preview shows what was actually uploaded. An empty
    slot means "nothing uploaded", and the storefront will draw its default -
    it does not mean the page has a hole in it.

    @var string $path
    @var string $input
    @var array  $field
    @var \App\Support\Content\PageContent $content
--}}
<div class="col-md-6">
    <div class="field">
        <label class="field-label">
            {{ $field['label'] }} <span class="field-opt">Optional</span>
        </label>

        <div data-image-picker>
            <div class="image-preview" data-image-preview @unless ($content->rawImage($path)) hidden @endunless>
                @if ($content->rawImage($path))
                    <img src="{{ $content->image($path) }}" alt="Current image">
                    <button type="button" class="ip-remove" data-image-clear aria-label="Remove image">
                        <i class="bi bi-x-lg"></i>
                    </button>
                @endif
            </div>

            <div class="image-picker" data-image-drop tabindex="0" role="button"
                 aria-label="Choose {{ $field['label'] }}">
                <div class="ip-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                <div class="ip-text"><b>Click to upload</b> or drag an image here</div>
                <input type="file" name="images[{{ $input }}]" accept="image/jpeg,image/png,image/webp">
            </div>

            {{-- The clear button writes this field's path here, and the service
                 reads it as "drop the upload and go back to the default". --}}
            <input type="hidden" name="remove_images[]" value=""
                   data-image-remove-flag data-remove-value="{{ $path }}">

            <p class="field-error mt-2" data-error-for="images.{{ $input }}"></p>
        </div>

        @if (! empty($field['hint']))
            <p class="field-hint">{{ $field['hint'] }}</p>
        @endif
    </div>
</div>
