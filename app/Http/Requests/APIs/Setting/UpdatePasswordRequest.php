<?php

namespace App\Http\Requests\APIs\Setting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Changing the signed-in operator's own password.
 *
 * current_password is checked by Laravel's rule against the admin guard, so a
 * stolen session cannot change the password without also knowing it - which is
 * the whole point of asking for it.
 */
class UpdatePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:admin'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'That is not your current password.',
            'password.confirmed' => 'The two new passwords do not match.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'current password',
            'password' => 'new password',
        ];
    }
}
