<?php

namespace App\Http\Requests\APIs\Chat;

class ChatHistoryRequest extends ChatSessionRequest
{
    public function rules(): array
    {
        return [
            // Backwards, for "load earlier messages".
            'before' => ['sometimes', 'uuid'],

            // Forwards, which is how the open widget picks up a reply typed by
            // a person without reloading the whole conversation.
            'after' => ['sometimes', 'uuid'],
        ];
    }
}
