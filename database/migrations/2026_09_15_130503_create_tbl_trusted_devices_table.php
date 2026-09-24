<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A browser that completed a one-time code. The browser holds a random
     * token; only its SHA-256 is kept here, so a leaked row cannot be replayed.
     */
    public function up(): void
    {
        Schema::create('tbl_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('tbl_admins')->cascadeOnDelete();

            $table->char('token_hash', 64)->unique();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('verified_at');
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['admin_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_trusted_devices');
    }
};
