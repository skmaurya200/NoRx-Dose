<?php

namespace App\Http\Requests\APIs\Review;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A review written in the panel.
 *
 * Wider than what a visitor may send: an operator picks the product, the
 * verified badge and the featured slot, because they are the one who knows
 * whether any of that is true.
 */
class StoreReviewRequest extends FormRequest
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
            'product_id' => ['nullable', 'integer', Rule::exists('tbl_products', 'id')->whereNull('deleted_at')],
            'author_name' => ['required', 'string', 'min:2', 'max:120'],
            'author_email' => ['nullable', 'string', 'email:rfc', 'max:180'],
            'location' => ['nullable', 'string', 'max:80'],
            'category' => ['required', Rule::in(array_keys(Review::CATEGORIES))],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_verified' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // A blank optional field must be null, not "", so "no location" is one
        // state in the database rather than two that render identically.
        foreach (['product_id', 'author_email', 'location', 'title'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'author_name' => 'name',
            'author_email' => 'email address',
            'body' => 'review',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->safe()->all();

        $data['is_featured'] = $this->boolean('is_featured');
        $data['is_verified'] = $this->boolean('is_verified');

        return $data;
    }
}
