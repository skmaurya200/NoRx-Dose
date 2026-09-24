{{--
    One "your own section" row: a heading, and a rich-text body under it.

    Rendered both by the form (for sections already saved) and by a <template>
    the repeater clones (for rows added in the browser), so the toolbar is
    written once. The template passes __INDEX__ as the index and the script
    swaps it for a unique one; PHP never sees that placeholder.

    @var string $name   the repeater's field name, e.g. "extra_sections"
    @var string|int $index
    @var string $label
    @var string $body
    @var string|null $placeholder
    @var bool|null $withIcon   an emoji shown in the tile beside the heading
    @var string|null $icon
--}}
@php
    $fieldId = $name.'-body-'.$index;
    $placeholder ??= "Heading, e.g. What's in the box";
    $withIcon ??= false;
    $icon ??= '';
@endphp

<div class="section-row @if ($withIcon) section-row--iconed @endif" data-section-row>
    @if ($withIcon)
        {{-- One character, shown in the gold tile on the storefront. Left blank
             it falls back to a plain mark, so a section without one still looks
             like the others. --}}
        <input type="text" class="field-input section-row__icon"
               name="{{ $name }}[{{ $index }}][icon]"
               value="{{ $icon }}" maxlength="8"
               placeholder="💊" aria-label="Section icon">
    @endif

    <input type="text" class="field-input section-row__label"
           name="{{ $name }}[{{ $index }}][label]"
           value="{{ $label }}" maxlength="80"
           placeholder="{{ $placeholder }}"
           aria-label="Section heading">

    <button type="button" class="section-row__remove" data-section-remove
            aria-label="Remove this section">
        <i class="bi bi-trash"></i>
    </button>

    {{-- The editor writes into this; it is the field the request and the
         sanitiser actually see. --}}
    <input type="hidden" id="{{ $fieldId }}" name="{{ $name }}[{{ $index }}][body]"
           value="{{ $body }}">

    <div class="editor section-row__body" data-editor data-editor-input="{{ $fieldId }}">
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
             aria-multiline="true" aria-label="Section text"
             data-placeholder="What goes under that heading…"></div>

        <textarea class="ed-source" data-editor-source hidden
                  spellcheck="false" aria-label="Section HTML"></textarea>
    </div>
</div>
