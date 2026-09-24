<?php

namespace App\Http\Requests\APIs\Coupon;

use App\Models\Coupon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreCouponRequest extends FormRequest
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
            // Letters, digits, dash and underscore only: a code with a space
            // or an accent in it cannot be read out over the phone or typed
            // reliably, and this one is meant to be shareable.
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', $this->uniqueCode()],

            'description' => ['nullable', 'string', 'max:160'],

            'type' => ['required', Rule::in(Coupon::TYPES)],

            // The ceiling depends on the type and is checked in the after()
            // hook below, where both values are in hand.
            'value' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],

            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99'],

            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],

            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],

            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function uniqueCode(): Unique
    {
        // withoutTrashed: a deleted code's name is free to reuse, and the
        // unique index only covers live rows in spirit - see the update
        // request for the edit case.
        return Rule::unique('tbl_coupons', 'code')->withoutTrashed();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') === 'percent' && (float) $this->input('value') > 100) {
                $validator->errors()->add('value', 'A percentage discount cannot be more than 100%.');
            }

            // A cap on a flat discount is meaningless - the flat amount is
            // already the cap - and having both invites the operator to set
            // two numbers that disagree.
            if ($this->input('type') === 'fixed' && $this->filled('max_discount_amount')) {
                $validator->errors()->add(
                    'max_discount_amount',
                    'A maximum only applies to a percentage discount.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code'))
                ? mb_strtoupper(trim($this->input('code')))
                : $this->input('code'),
        ]);

        // A blank optional field must end up null rather than 0 or "", or
        // "no cap" and "a cap of $0.00" stop being distinguishable. Laravel's
        // ConvertEmptyStringsToNull usually gets here first; this covers the
        // clients it does not run for.
        foreach (['description', 'max_discount_amount', 'usage_limit', 'starts_at', 'ends_at'] as $key) {
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
            'min_order_amount' => 'minimum order amount',
            'max_discount_amount' => 'maximum discount',
            'usage_limit' => 'usage limit',
            'starts_at' => 'start date',
            'ends_at' => 'end date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dashes and underscores only.',
            'code.unique' => 'That code already exists.',
            'ends_at.after' => 'The end date must be after the start date.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->safe()->all();

        $data['min_order_amount'] = (float) ($data['min_order_amount'] ?? 0);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $this->boolean('is_active');
        $data['is_public'] = $this->boolean('is_public');

        return $data;
    }
}
