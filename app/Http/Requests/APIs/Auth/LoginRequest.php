<?php

namespace App\Http\Requests\APIs\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Open endpoint - the credentials themselves are the authorisation.
     */
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
            // A username; an email or phone number still works for accounts
            // that have none. The service decides which.
            // Length is capped so a huge body cannot be used to burn hashing time.
            'login' => ['required', 'string', 'min:3', 'max:191'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'remember' => ['sometimes', 'boolean'],

            // Present only for non-browser clients: supplying it switches the
            // response from a session to a bearer token.
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],

            // An API client's way of presenting the trusted-device token a
            // browser keeps in its cookie.
            'trusted_device_token' => ['sometimes', 'nullable', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'login' => 'username',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required' => 'Enter your username.',
            'password.required' => 'Enter your password.',
            'password.min' => 'Your password must be at least 8 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => is_string($this->input('login')) ? trim($this->input('login')) : $this->input('login'),
            'remember' => $this->boolean('remember'),
        ]);
    }

    public function credentials(): array
    {
        return [
            'login' => (string) $this->validated('login'),
            'password' => (string) $this->validated('password'),
        ];
    }

    public function wantsToken(): bool
    {
        return filled($this->validated('device_name'));
    }

    public function deviceName(): string
    {
        return (string) ($this->validated('device_name') ?: 'api');
    }
}
