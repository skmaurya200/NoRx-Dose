<?php

namespace App\Http\Requests\APIs\Chat;

class ChatContactRequest extends ChatSessionRequest
{
    public function rules(): array
    {
        return [
            'session_uuid' => ['required', 'uuid'],
            'message_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'-]+$/u"],
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'phone' => ['required', 'string', 'regex:/^[6-9][0-9]{9}$/D'],
            'message' => ['required', 'string', 'min:3', 'max:2000', 'regex:/[\p{L}\p{N}]/u'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Enter a valid name using letters, spaces, apostrophes or hyphens.',
            'phone.regex' => 'Enter a 10-digit Indian mobile number starting with 6, 7, 8 or 9.',
        ];
    }
}
