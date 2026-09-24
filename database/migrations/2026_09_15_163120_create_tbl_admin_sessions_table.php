<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per signed-in panel browser session: who is signed in right now,
     * since when, and how many pages they have opened.
     *
     * The session id is a credential, so only its SHA-256 is stored - a read of
     * this table cannot be turned into a hijacked session.
     */
    public function up(): void
    {
        Schema::create('tbl_admin_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('tbl_admins')->cascadeOnDelete();

            $table->char('session_hash', 64)->unique();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('logged_in_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('logged_out_at')->nullable();

            $table->unsignedInteger('page_views')->default(0);
            $table->string('last_page', 191)->nullable();
            $table->string('last_url', 500)->nullable();

            $table->timestamps();

            $table->index(['logged_out_at', 'last_seen_at']);
            $table->index(['admin_id', 'logged_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_admin_sessions');
    }
};
