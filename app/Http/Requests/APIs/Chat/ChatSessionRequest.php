<?php

namespace App\Http\Requests\APIs\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChatSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        foreach (['message', 'name', 'email', 'phone'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/^\s+|\s+$/u', '', $this->input($field))]);
            }
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->input()), array_keys($this->rules()), ['_token']) as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        }];
    }
}
