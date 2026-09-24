{{--
    One row of a repeater, and the blank one the "Add" button clones.

    A null $index means this is the template: its inputs carry no name, because
    the JS assigns one from the row's position after every add, move or remove.

    @var array      $subFields  the sub-fields from the schema
    @var string     $input      the repeater's wire name
    @var int|null   $index
    @var array      $row
--}}
@php
    $isTemplate = $index === null;
@endphp

<div class="repeat-row">
    <span class="repeat-row__n">{{ $isTemplate ? '' : $index + 1 }}</span>

    <div class="repeat-row__body">
        @foreach ($subFields as $key => $sub)
            @php
                $name = $isTemplate ? null : "repeaters[{$input}][{$index}][{$key}]";
                $value = $row[$key] ?? '';
            @endphp

            @if (($sub['type'] ?? 'text') === 'textarea')
                <textarea class="field-textarea" rows="2" maxlength="2000"
                          @if ($name) name="{{ $name }}" @else data-repeat-field="{{ $key }}" @endif
                          placeholder="{{ $sub['label'] }}"
                          aria-label="{{ $sub['label'] }}">{{ $value }}</textarea>
            @else
                <input type="text" class="field-input" maxlength="500" value="{{ $value }}"
                       @if ($name) name="{{ $name }}" @else data-repeat-field="{{ $key }}" @endif
                       placeholder="{{ $sub['label'] }}"
                       aria-label="{{ $sub['label'] }}">
            @endif
        @endforeach
    </div>

    <div class="repeat-row__acts">
        <button type="button" class="row-btn" data-repeat-up title="Move up" aria-label="Move up">
            <i class="bi bi-chevron-up"></i>
        </button>
        <button type="button" class="row-btn" data-repeat-down title="Move down" aria-label="Move down">
            <i class="bi bi-chevron-down"></i>
        </button>
        <button type="button" class="row-btn row-btn--danger" data-repeat-remove title="Remove" aria-label="Remove">
            <i class="bi bi-trash"></i>
        </button>
    </div>
</div>
