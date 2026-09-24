<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_session_id' => ChatSession::factory(),
            'message_uuid' => (string) Str::uuid(),
            'request_uuid' => (string) Str::uuid(),
            'sender' => 'user',
            'message' => 'Show me available products.',
            'message_type' => 'text',
        ];
    }
}
