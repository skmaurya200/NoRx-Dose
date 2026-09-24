<?php

namespace App\Http\Requests\APIs\Chat;

class ChatProductsRequest extends ChatSessionRequest
{
    public function rules(): array
    {
        return ['page' => ['required', 'integer', 'min:2', 'max:10000']];
    }
}
