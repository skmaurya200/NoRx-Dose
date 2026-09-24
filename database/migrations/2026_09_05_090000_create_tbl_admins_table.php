<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 191)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password');

            // Uploads live under public/, never storage/, so this holds a path
            // relative to the public directory (e.g. uploads/admins/ab12.jpg).
            $table->string('avatar_path', 255)->nullable();

            $table->string('role', 40)->default('manager');
            $table->boolean('is_active')->default(true);

            // Brute-force state. locked_until is authoritative; the counter only
            // decides when to set it.
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('password_changed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_admins');
    }
};
