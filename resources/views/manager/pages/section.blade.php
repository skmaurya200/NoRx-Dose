{{--
    One section of a page - the contents of one tab.

    Dispatches each field to the partial for its type, so this file stays a
    layout and knows nothing about how a text box or an image picker is drawn.

    @var string $pageKey
    @var string $sectionKey
    @var array  $section
    @var array  $page
    @var string $liveUrl
    @var \App\Support\Content\PageContent $content
--}}
<div class="row g-3">
    <div class="col-lg-9">
        <div class="form-card">
            <h2 class="form-card-title">{{ $section['name'] }}</h2>

            @if (! empty($section['description']))
                <p class="form-card-sub">{{ $section['description'] }}</p>
            @endif

            <div class="row g-3">
                @foreach ($section['fields'] as $fieldKey => $field)
                    @php
                        $path = $sectionKey.'.'.$fieldKey;
                        // A dot is Laravel's array separator in a field name,
                        // so the wire name uses "__" and the request maps back.
                        $input = \App\Support\Content\PageSchema::inputName($path);
                        $type = $field['type'] ?? 'text';
                    @endphp

                    @include('manager.pages.fields.'.(in_array($type, ['image', 'repeater']) ? $type : 'text'), [
                        'path' => $path,
                        'input' => $input,
                        'field' => $field,
                        'saved' => $content->saved($path) ?? '',
                    ])
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="form-card">
            <h2 class="form-card-title">This section</h2>

            <dl class="kv kv--tight">
                <div class="kv-row"><dt>Page</dt><dd>{{ $page['name'] }}</dd></div>
                <div class="kv-row"><dt>Address</dt><dd>{{ parse_url($liveUrl, PHP_URL_PATH) ?: '/' }}</dd></div>
                <div class="kv-row"><dt>Fields</dt><dd>{{ count($section['fields']) }}</dd></div>
            </dl>

            <p class="field-hint mb-0">
                Every tab saves together, so you can edit several sections before
                pressing Save once.
            </p>
        </div>

        <div class="form-actions">
            <a href="{{ route('manager.pages.index') }}" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-gold">
                <span class="spinner" aria-hidden="true"></span>
                Save changes
            </button>
        </div>
    </div>
</div>
