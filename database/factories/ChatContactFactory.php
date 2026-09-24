<?php

namespace Database\Factories;

use App\Models\ChatContact;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatContact>
 */
class ChatContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_message_id' => ChatMessage::factory(),
            'chat_session_id' => fn (array $attributes) => ChatMessage::findOrFail($attributes['chat_message_id'])->chat_session_id,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '9876543210',
            'message' => 'Please help with my question.',
        ];
    }
}
