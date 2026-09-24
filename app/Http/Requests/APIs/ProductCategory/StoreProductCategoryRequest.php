<?php

namespace App\Http\Requests\APIs\ProductCategory;

use App\Support\PublicUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    /**
     * The route already sits behind auth:sanctum + admin, so reaching this
     * point means the caller is a live, active admin.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],

            // Optional override. Left blank the slug is derived from the name;
            // typed, it is still slugified and de-duplicated by the service, so
            // a client cannot claim an arbitrary URL.
            'slug' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],

            // exists() rather than a bare integer: a parent id pointing at a
            // deleted or non-existent row would create an orphan branch. The
            // soft-delete check is explicit because exists ignores it.
            'parent_id' => [
                'nullable',
                Rule::exists('tbl_product_categories', 'id')->whereNull('deleted_at'),
            ],

            // Rich text from the panel's editor, so the ceiling is markup
            // length rather than reading length. HtmlSanitizer decides what
            // actually survives into the column.
            'description' => ['nullable', 'string', 'max:20000'],

            // Expandable sections on the category page. Same flag as the
            // product form's sections: a repeater whose every row has been
            // deleted posts no key at all, and without this "delete the last
            // one" would silently keep it.
            'accordions_present' => ['sometimes', 'boolean'],
            'accordions' => ['nullable', 'array', 'max:12'],
            'accordions.*.label' => ['nullable', 'string', 'max:120'],

            // One glyph for the tile beside the heading. Short enough that it
            // cannot be a word, long enough for an emoji that is several code
            // points on its own.
            'accordions.*.icon' => ['nullable', 'string', 'max:8'],
            'accordions.*.body' => ['nullable', 'string', 'max:20000'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:255'],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],

            // Mime and size come from config, so the limit is stated once.
            // PublicUpload re-checks the file's own bytes before writing it.
            'image' => PublicUpload::rules('categories'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the category a name.',
            'accordions.max' => 'A category can carry up to 12 sections.',
            'slug.regex' => 'A URL can only use letters, numbers and single dashes.',
            'parent_id.exists' => 'That parent category no longer exists.',
            'image.image' => 'The category image must be a picture file.',
            'image.max' => 'The category image must be 2 MB or smaller.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),

            // A <select> with no parent posts "", which is not a valid foreign
            // key; normalise it to null before the rules run.
            'parent_id' => $this->input('parent_id') === '' ? null : $this->input('parent_id'),

            // An untouched slug field posts "", which must read as "work it
            // out from the name" rather than as an empty URL.
            'slug' => is_string($this->input('slug')) && trim($this->input('slug')) !== ''
                ? trim($this->input('slug'))
                : null,

            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
        ]);
    }

    /**
     * The columns the model is allowed to receive. The uploaded file is handled
     * separately by the service, never mass-assigned.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = [
            'name' => $this->validated('name'),
            'slug' => $this->validated('slug'),
            'parent_id' => $this->validated('parent_id'),
            'description' => $this->validated('description'),
            'meta_title' => $this->validated('meta_title'),
            'meta_description' => $this->validated('meta_description'),
            'sort_order' => (int) ($this->validated('sort_order') ?? 0),
            'is_active' => (bool) $this->validated('is_active'),
            'is_featured' => (bool) $this->validated('is_featured'),
        ];

        // The key is left out entirely when the request did not mention the
        // sections, because the service reads "absent" as leave them alone.
        // Present but empty - the flag with no rows - is what clears them.
        if ($this->has('accordions')) {
            $payload['accordions'] = $this->validated('accordions');
        } elseif ($this->boolean('accordions_present')) {
            $payload['accordions'] = [];
        }

        return $payload;
    }
}
