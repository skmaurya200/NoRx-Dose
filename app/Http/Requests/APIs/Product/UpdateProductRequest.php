<?php

namespace App\Http\Requests\APIs\Product;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['remove_thumbnail'] = ['sometimes', 'boolean'];

        return $rules;
    }

    public function shouldRemoveThumbnail(): bool
    {
        return $this->boolean('remove_thumbnail');
    }

    /**
     * The product being edited must not collide with its own SKU.
     */
    protected function uniqueSku(): Unique
    {
        return Rule::unique('tbl_products', 'sku')->ignore($this->route('product')->id);
    }
}
