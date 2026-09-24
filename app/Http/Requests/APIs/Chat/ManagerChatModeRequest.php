<?php

namespace App\Http\Requests\APIs\Chat;

use App\Models\ChatSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The AI / manual switch on one conversation.
 */
class ManagerChatModeRequest extends FormRequest
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
            'reply_mode' => ['required', 'string', Rule::in(ChatSession::MODES)],

            // Where the screen has read up to, so the response can carry the
            // handover notice the switch just wrote.
            'after' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reply_mode.in' => 'A conversation is answered either by the assistant or by a person.'];
    }
}
