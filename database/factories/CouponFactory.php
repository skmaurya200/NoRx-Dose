<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SAVE'.Str::upper(Str::random(5)),
            'description' => null,
            'type' => 'percent',
            'value' => 10,
            'min_order_amount' => 0,
            'max_discount_amount' => null,
            'usage_limit' => null,
            'used_count' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
        ];
    }

    public function percent(float $value, float $minimum = 0): static
    {
        return $this->state(fn () => [
            'type' => 'percent',
            'value' => $value,
            'min_order_amount' => $minimum,
        ]);
    }

    public function fixed(float $value, float $minimum = 0): static
    {
        return $this->state(fn () => [
            'type' => 'fixed',
            'value' => $value,
            'min_order_amount' => $minimum,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['starts_at' => now()->addWeek()]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => ['usage_limit' => 5, 'used_count' => 5]);
    }
}
