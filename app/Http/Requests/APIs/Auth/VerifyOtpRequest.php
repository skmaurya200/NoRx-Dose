<?php

namespace App\Http\Requests\APIs\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The second sign-in step. A browser's challenge is read from its session; an
 * API client sends back the challenge_token the login response gave it.
 */
class VerifyOtpRequest extends FormRequest
{
    /**
     * Open endpoint - the challenge and the code are the authorisation.
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
            'code' => ['required', 'string', 'digits:6'],
            'challenge_token' => ['sometimes', 'nullable', 'string', 'max:128'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Enter the 6-digit verification code.',
            'code.digits' => 'Invalid or expired OTP.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // People paste "123 456" out of an email.
        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', $this->input('code'))]);
        }
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }

    public function challenge(): ?string
    {
        $challenge = $this->validated('challenge_token');

        return is_string($challenge) && $challenge !== '' ? $challenge : null;
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
