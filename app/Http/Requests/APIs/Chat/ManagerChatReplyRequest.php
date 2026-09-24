<?php

namespace App\Http\Requests\APIs\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A reply typed by an admin in the panel.
 *
 * Deliberately not a subclass of ChatSessionRequest: that one exists to keep a
 * guest's payload to exactly the fields the widget sends, and an admin form is
 * a different surface with a different guard in front of it.
 */
class ManagerChatReplyRequest extends FormRequest
{
    /**
     * The route sits behind auth:sanctum + admin, so reaching here means the
     * caller is a live, active admin.
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
            // The same ceiling as a guest message: a reply is a chat bubble,
            // and anything longer belongs in an email.
            'message' => ['required', 'string', 'min:1', 'max:2000', 'regex:/[\p{L}\p{N}]/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Write a reply first.',
            'message.regex' => 'A reply needs some words in it.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => preg_replace('/^\s+|\s+$/u', '', $this->input('message'))]);
        }
    }
}
