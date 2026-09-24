<?php

namespace App\Http\Requests\APIs\Chat;

class ChatMessageRequest extends ChatSessionRequest
{
    public function rules(): array
    {
        return [
            'session_uuid' => ['required', 'uuid'],
            'request_uuid' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:1000', 'regex:/[\p{L}\p{N}]/u'],
        ];
    }
}
