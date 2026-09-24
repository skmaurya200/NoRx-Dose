<?php

namespace Database\Factories;

use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatSession>
 */
class ChatSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_uuid' => (string) Str::uuid(),
            'owner_hash' => hash('sha256', Str::random(64)),
        ];
    }
}
