{{--
    One line of copy, or a paragraph.

    The original wording is the placeholder rather than the value: the operator
    can see what they are replacing, and clearing the box restores it.

    @var string $path   "section.field"
    @var string $input  the wire name, with "__" for the dot
    @var array  $field  the schema entry
    @var string $saved  what was saved, or ""
--}}
@php
    $id = str_replace('.', '_', $path);
    $default = $field['default'] ?? '';
    $isLong = ($field['type'] ?? 'text') === 'textarea';
@endphp

<div class="{{ $isLong ? 'col-12' : 'col-md-6' }}">
    <div class="field">
        <label class="field-label" for="{{ $id }}">{{ $field['label'] }}</label>

        @if ($isLong)
            <textarea class="field-textarea" id="{{ $id }}" name="fields[{{ $input }}]"
                      rows="3" maxlength="2000" placeholder="{{ $default }}"
                      aria-describedby="{{ $id }}-error">{{ $saved }}</textarea>
        @else
            <input type="text" class="field-input" id="{{ $id }}" name="fields[{{ $input }}]"
                   value="{{ $saved }}" maxlength="500" placeholder="{{ $default }}"
                   aria-describedby="{{ $id }}-error">
        @endif

        <p class="field-error" id="{{ $id }}-error" data-error-for="fields.{{ $input }}"></p>

        @if (! empty($field['hint']))
            <p class="field-hint">{{ $field['hint'] }}</p>
        @endif

        @if (($field['type'] ?? 'text') === 'inline')
            <p class="field-hint">
                Only <code>&lt;em&gt;</code>, <code>&lt;strong&gt;</code> and
                <code>&lt;br&gt;</code> are kept. Anything else is stripped.
            </p>
        @endif
    </div>
</div>
