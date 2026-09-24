<?php

namespace App\Http\Requests\APIs\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderProductImagesRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1', 'max:50'],

            // Scoped to this product's own images, so a posted id belonging to
            // another product is rejected here rather than silently ignored
            // later. distinct catches a duplicated id, which would otherwise
            // leave two images fighting over one position.
            'order.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tbl_product_images', 'id')
                    ->where('product_id', $this->route('product')->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order.required' => 'Nothing to reorder.',
            'order.*.exists' => 'One of those images does not belong to this product.',
            'order.*.distinct' => 'The same image was listed twice.',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function orderedIds(): array
    {
        return array_map('intval', $this->validated('order'));
    }
}
