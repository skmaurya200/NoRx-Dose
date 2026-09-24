<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatProductResult;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatProductResult>
 */
class ChatProductResultFactory extends Factory
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
            'product_id' => Product::factory(),
            'position' => 1,
            'snapshot' => ['name' => 'Recorded product'],
        ];
    }
}
