<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * A pending review about the shop, which is what the storefront form
     * produces. Everything else is a state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => null,
            'order_id' => null,
            'author_name' => fake()->name(),
            'author_email' => fake()->unique()->safeEmail(),
            'location' => fake()->city(),
            'category' => fake()->randomElement(array_keys(Review::CATEGORIES)),
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => 'pending',
            'is_featured' => false,
            'is_verified' => false,
            'helpful_count' => 0,
            'source' => 'customer',
            'approved_at' => null,
            'approved_by' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * Published - the only state the storefront ever reads.
     */
    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }

    public function featured(): static
    {
        return $this->approved()->state(fn () => ['is_featured' => true]);
    }

    public function byManager(): static
    {
        return $this->approved()->state(fn () => ['source' => 'manager']);
    }
}
