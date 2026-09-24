{{--
    A list of rows - the home page's FAQ, for instance.

    Rows and the blank one that "Add" clones share a partial, so the two can
    never drift apart. The JS names the inputs from their position, which is
    why the template's fields carry data-repeat-field instead of a name.

    @var string $path
    @var string $input
    @var array  $field
    @var \App\Support\Content\PageContent $content
--}}
@php
    $rows = $content->items($path);
    $item = Str::lower($field['item_label'] ?? 'row');
@endphp

<div class="col-12">
    <div class="field mb-0">
        <label class="field-label">
            {{ $field['label'] }}
            <span class="field-opt" data-repeat-count>{{ count($rows) }}</span>
        </label>

        <div class="repeat" data-content-repeater
             data-repeat-name="repeaters[{{ $input }}]"
             data-repeat-max="{{ $field['max'] ?? 30 }}"
             data-repeat-label="{{ $field['item_label'] ?? 'Row' }}">

            <div data-repeat-rows>
                @foreach ($rows as $i => $row)
                    @include('manager.pages.fields.repeater-row', [
                        'subFields' => $field['fields'],
                        'input' => $input,
                        'index' => $i,
                        'row' => $row,
                    ])
                @endforeach
            </div>

            <template data-repeat-template>
                @include('manager.pages.fields.repeater-row', [
                    'subFields' => $field['fields'],
                    'input' => $input,
                    'index' => null,
                    'row' => [],
                ])
            </template>

            <button type="button" class="btn-ghost mt-2" data-repeat-add>
                <i class="bi bi-plus-lg"></i> Add {{ $item }}
            </button>
        </div>

        <p class="field-error mt-2" data-error-for="repeaters.{{ $input }}"></p>

        @if (! empty($field['hint']))
            <p class="field-hint">{{ $field['hint'] }}</p>
        @endif
    </div>
</div>
