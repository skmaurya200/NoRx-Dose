<?php

namespace App\Http\Requests\APIs\User;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Add User. Exactly five fields - the role is not one of them, so nothing
 * posted here can create an Admin or a Manager.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * A username has to start and end with a letter or digit, contain at least
     * one letter (so it can never be read as a phone number at sign-in) and
     * may use dots, dashes and underscores in between.
     */
    public const USERNAME_PATTERN = '/^(?=.*[a-z])[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/';

    public function authorize(): bool
    {
        return $this->user()?->can('create', Admin::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:'.self::USERNAME_PATTERN, $this->uniqueRule('username')],
            'email' => ['required', 'string', 'max:191', 'email:rfc,strict', $this->uniqueRule('email')],
            // Stored the way sign-in looks phones up: digits only.
            'mobile_no' => ['required', 'string', 'digits_between:10,15', $this->uniqueRule('phone')],
            'password' => $this->passwordRules(required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Use letters, numbers, dots, dashes or underscores, with at least one letter.',
            'username.unique' => 'That username is already taken.',
            'email.unique' => 'An account with that email address already exists.',
            'mobile_no.digits_between' => 'Enter a valid mobile number of 10 to 15 digits.',
            'mobile_no.unique' => 'An account with that mobile number already exists.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mobile_no' => 'mobile no',
        ];
    }

    /**
     * @return array{name: string, username: string, email: string, mobile_no: string, password: string}
     */
    public function payload(): array
    {
        return $this->safe()->only(['name', 'username', 'email', 'mobile_no', 'password']);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->trimmed('name'),
            'username' => is_string($this->input('username')) ? Str::lower(trim($this->input('username'))) : $this->input('username'),
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
            // "+91 98765-43210" is stored, and matched at sign-in, as digits.
            'mobile_no' => is_string($this->input('mobile_no'))
                ? (preg_match('/^\+?[\d\s().-]+$/', trim($this->input('mobile_no'))) === 1
                    ? preg_replace('/\D+/', '', $this->input('mobile_no'))
                    : trim($this->input('mobile_no')))
                : $this->input('mobile_no'),
        ]);
    }

    /**
     * Soft-deleted accounts still hold their unique values in the table, so
     * they are counted here rather than failing on the database index.
     */
    protected function uniqueRule(string $column): mixed
    {
        return Rule::unique('tbl_admins', $column);
    }

    /**
     * @return array<int, mixed>
     */
    protected function passwordRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:255',
            Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
        ];
    }

    private function trimmed(string $key): mixed
    {
        return is_string($this->input($key)) ? trim($this->input($key)) : $this->input($key);
    }
}
