<?php

namespace App\Http\Requests\APIs\Review;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A review submitted from the storefront.
 *
 * Narrower than the panel's on purpose. Everything that decides whether a
 * review is believed - its status, whether it is verified, whether it is
 * featured - is absent here, because none of it is a visitor's to claim.
 */
class SubmitReviewRequest extends FormRequest
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
            // Only a product that is actually on sale. A review of a draft is
            // a review of something nobody can have bought.
            'product_id' => [
                'nullable', 'integer',
                Rule::exists('tbl_products', 'id')->where('status', 'active')->whereNull('deleted_at'),
            ],
            'author_name' => ['required', 'string', 'min:2', 'max:120'],

            // Required here though nullable in the panel: it is what ties a
            // review to an order, and so what earns the verified badge.
            'author_email' => ['required', 'string', 'email:rfc', 'max:180'],

            'location' => ['nullable', 'string', 'max:80'],
            // Optional from the storefront: the form stopped asking, because a
            // customer writing about a product is writing about the product.
            // Absent, the column's own default applies. The panel still sets it
            // deliberately - see StoreReviewRequest.
            'category' => ['nullable', Rule::in(array_keys(Review::CATEGORIES))],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'author_email' => is_string($this->input('author_email'))
                ? mb_strtolower(trim($this->input('author_email')))
                : $this->input('author_email'),
        ]);

        foreach (['product_id', 'location', 'title', 'category'] as $key) {
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
            'rating' => 'star rating',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Pick a star rating first.',
            'body.min' => 'Tell us a little more — a sentence or two is plenty.',
        ];
    }

    /**
     * Everything the service needs. The IP is taken from the request rather
     * than the payload, for the obvious reason.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->all() + ['ip_address' => $this->ip()];
    }
}
