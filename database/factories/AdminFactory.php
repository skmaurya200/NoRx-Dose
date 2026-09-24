<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->regexify('[a-z]{6}[0-9]{3}'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('91##########'),
            'password' => 'Password123!',
            'role' => 'manager',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Admin::ROLE_ADMIN]);
    }

    public function user(): static
    {
        return $this->state(fn () => ['role' => Admin::ROLE_USER]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'failed_login_attempts' => 8,
            'locked_until' => now()->addMinutes(15),
        ]);
    }
}
