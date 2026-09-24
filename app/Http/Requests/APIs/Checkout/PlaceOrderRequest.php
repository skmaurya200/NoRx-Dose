<?php

namespace App\Http\Requests\APIs\Checkout;

use App\Support\PaymentCard;
use App\Support\UsStates;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Everything the checkout page posts, validated as if the page did not exist.
 *
 * The browser runs the same checks for the sake of a quick red outline, but
 * none of that reaches the server, so all of it is restated here: an order
 * placed with curl gets exactly the same treatment as one placed by a person.
 */
class PlaceOrderRequest extends FormRequest
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
        return array_merge(
            $this->billingRules(),
            $this->basketRules(),
            $this->paymentRules(),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function billingRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:180'],

            // Deliberately loose on shape and strict on length: people write
            // "+1 (415) 555-0132" and every one of those is a real number.
            'phone' => ['required', 'string', 'min:7', 'max:32', 'regex:/^[0-9+()\-.\s]+$/'],

            // The store ships to one country, so this is not a free choice -
            // a payload naming anywhere else is rejected rather than quietly
            // corrected, or the customer would be told nothing was wrong.
            'country' => ['required', 'string', Rule::in([config('shop.country', 'US')])],

            'state' => ['required', 'string', 'size:2', Rule::in(UsStates::codes())],

            // Five digits, or ZIP+4. Anything else is a typo, not an address.
            'postal_code' => ['required', 'string', 'regex:/^\d{5}(-\d{4})?$/'],

            'street' => ['required', 'string', 'min:4', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function basketRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.config('shop.max_order_lines', 50)],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.pack_label' => ['nullable', 'string', 'max:60'],
            'items.*.pack_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('shop.max_line_quantity', 99)],

            // Prices are never read from the payload, so they are not accepted
            // from it either - a field that is ignored but allowed invites the
            // belief that sending it does something.
            'items.*.price' => ['prohibited'],

            'shipping_method' => ['required', 'string', Rule::in(array_column(config('shop.shipping', []), 'id'))],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function paymentRules(): array
    {
        return [
            'card_holder' => ['required', 'string', 'min:2', 'max:120'],
            'card_number' => ['required', 'string', 'max:32'],
            'card_expiry' => ['required', 'string', 'max:10'],
            'card_cvc' => ['required', 'string', 'max:4'],
        ];
    }

    /**
     * The card checks, which need more than a regex.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $number = $this->input('card_number');

            if (! PaymentCard::passesLuhn($number)) {
                $validator->errors()->add('card_number', 'Please enter a valid card number.');

                // No point telling them the CVC is the wrong length for a
                // number we have already said is wrong.
                return;
            }

            $expiry = PaymentCard::parseExpiry($this->input('card_expiry'));

            if ($expiry === null) {
                $validator->errors()->add('card_expiry', 'Enter the expiry date as MM / YY.');
            } elseif (PaymentCard::isExpired($expiry['month'], $expiry['year'])) {
                $validator->errors()->add('card_expiry', 'That card has expired.');
            }

            if (! PaymentCard::isValidCvc($this->input('card_cvc'), $number)) {
                $length = PaymentCard::cvcLength($number);

                $validator->errors()->add(
                    'card_cvc',
                    'The security code on this card is '.$length.' digits.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'state' => is_string($this->input('state')) ? mb_strtoupper(trim($this->input('state'))) : $this->input('state'),
            'country' => is_string($this->input('country'))
                ? mb_strtoupper(trim($this->input('country')))
                : config('shop.country', 'US'),
            'coupon_code' => is_string($this->input('coupon_code')) && trim($this->input('coupon_code')) !== ''
                ? mb_strtoupper(trim($this->input('coupon_code')))
                : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'postal_code' => 'postal code',
            'card_holder' => 'card holder',
            'card_number' => 'card number',
            'card_expiry' => 'expiry date',
            'card_cvc' => 'security code',
            'shipping_method' => 'shipping method',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country.in' => 'We currently ship within the United States only.',
            'state.in' => 'Choose a US state.',
            'state.size' => 'Choose a US state.',
            'postal_code.regex' => 'Enter a US ZIP code, e.g. 94107 or 94107-1234.',
            'phone.regex' => 'A phone number can only contain digits, spaces and + ( ) - characters.',
            'items.required' => 'Your cart is empty.',
            'items.min' => 'Your cart is empty.',
            'items.*.price.prohibited' => 'Prices are set by the store, not by the browser.',
        ];
    }

    /**
     * Everything the checkout service needs, in the shape it expects.
     *
     * card_cvc is carried only while config('shop.store_card_cvc') is on. With
     * it off this method is where the code stops, which was this project's
     * original behaviour - see the migration that added the column.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $valid = $this->safe();

        return [
            'items' => array_values($valid->input('items', [])),
            'shipping_method' => $valid->input('shipping_method'),
            'coupon_code' => $valid->input('coupon_code'),

            'first_name' => $valid->input('first_name'),
            'last_name' => $valid->input('last_name'),
            'email' => $valid->input('email'),
            'phone' => $valid->input('phone'),
            'street' => $valid->input('street'),
            'city' => $valid->input('city'),
            'state' => $valid->input('state'),
            'postal_code' => $valid->input('postal_code'),
            'country' => $valid->input('country'),
            'notes' => $valid->input('notes'),

            'card_holder' => $valid->input('card_holder'),
            'card_number' => $valid->input('card_number'),
            'card_expiry' => $valid->input('card_expiry'),
            'card_cvc' => config('shop.store_card_cvc')
                ? $valid->input('card_cvc')
                : null,

            'ip' => $this->ip(),
        ];
    }
}
