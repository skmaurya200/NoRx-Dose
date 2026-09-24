<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Creates the first back-office account.
     *
     * The password is read from ADMIN_SEED_PASSWORD so a real one is never
     * committed; without it a random password is generated and printed once,
     * which is safer than seeding a guessable default into every environment.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_SEED_EMAIL', 'admin@aurumwellness.test');
        $password = (string) env('ADMIN_SEED_PASSWORD', '');
        $generated = false;

        if ($password === '') {
            $password = Str::password(16, symbols: false);
            $generated = true;
        }

        $admin = Admin::query()->updateOrCreate(
            ['email' => Str::lower($email)],
            [
                'name' => (string) env('ADMIN_SEED_NAME', 'Store Manager'),
                'password' => $password,
                'role' => 'super-admin',
                'is_active' => true,
                'password_changed_at' => now(),
            ],
        );

        $this->command?->info("Admin ready: {$admin->email}");

        if ($generated) {
            $this->command?->warn("Generated password (shown once): {$password}");
            $this->command?->warn('Sign in and change it, or set ADMIN_SEED_PASSWORD in .env before seeding.');
        }
    }
}
