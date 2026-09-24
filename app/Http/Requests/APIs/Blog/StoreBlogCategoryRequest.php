<?php

namespace App\Http\Requests\APIs\Blog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreBlogCategoryRequest extends FormRequest
{
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
            // Unique on the name, not the slug: the slug is derived and would
            // silently become "routines-2", which reads like a bug to whoever
            // created it.
            'name' => ['required', 'string', 'min:2', 'max:120', $this->uniqueName()],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function uniqueName(): Unique
    {
        return Rule::unique('tbl_blog_categories', 'name')->withoutTrashed();
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with that name already exists.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->safe()->all();

        $data['is_active'] = $this->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
