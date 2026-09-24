<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per pending sign-in. Neither the code nor the challenge is stored
     * in the clear: both are keyed hashes, so a database read cannot be turned
     * into a completed sign-in.
     */
    public function up(): void
    {
        Schema::create('tbl_login_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('tbl_admins')->cascadeOnDelete();

            $table->char('challenge_hash', 64)->unique();
            $table->char('code_hash', 64);

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('send_count')->default(1);
            $table->timestamp('last_sent_at')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();

            $table->index(['admin_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_login_otps');
    }
};
