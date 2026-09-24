<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * An anonymous failed sign-in: the most common row in a real log.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => null,
            'username' => fake()->userName(),
            'role' => null,
            'action' => ActivityLog::LOGIN_FAILED,
            'description' => 'Failed sign-in attempt.',
            'status' => ActivityLog::STATUS_FAILED,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'method' => 'POST',
            'url' => '/api/manager/auth/login',
            'properties' => null,
        ];
    }
}
