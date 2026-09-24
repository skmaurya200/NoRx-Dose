<?php

namespace App\Http\Requests\APIs\ProductCategory;

/**
 * Same shape as the store request, plus the two things only an edit needs:
 * the ability to clear an existing image, and a parent that cannot be the
 * record itself.
 */
class UpdateProductCategoryRequest extends StoreProductCategoryRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // The deeper check - a parent that is one of this category's own
        // descendants - needs to walk the tree, so it lives in the service.
        $rules['parent_id'][] = 'not_in:'.$this->route('category')->id;

        $rules['remove_image'] = ['sometimes', 'boolean'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'parent_id.not_in' => 'A category cannot be its own parent.',
        ];
    }

    public function shouldRemoveImage(): bool
    {
        return $this->boolean('remove_image');
    }
}
