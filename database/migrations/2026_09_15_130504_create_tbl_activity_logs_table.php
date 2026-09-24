<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An append-only audit trail. The username and role are copied onto the row
     * rather than joined, so an entry still reads correctly after the account
     * is renamed, demoted or deleted.
     */
    public function up(): void
    {
        Schema::create('tbl_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('tbl_admins')->nullOnDelete();

            $table->string('username', 191)->nullable();
            $table->string('role', 40)->nullable();

            $table->string('action', 60);
            $table->string('description', 500);
            $table->string('status', 20);

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 500)->nullable();

            // Non-secret context only - never a payload.
            $table->json('properties')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
            $table->index('role');
            $table->index('status');
            $table->index('ip_address');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_activity_logs');
    }
};
