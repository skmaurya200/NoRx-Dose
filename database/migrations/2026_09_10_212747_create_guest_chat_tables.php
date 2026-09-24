<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tbl_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_uuid')->unique();
            $table->char('owner_hash', 64)->unique();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('tbl_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_uuid')->unique();
            $table->foreignId('chat_session_id')->constrained('tbl_chat_sessions')->cascadeOnDelete();
            $table->uuid('request_uuid')->nullable();
            $table->foreignId('reply_to_id')->nullable()->unique()->constrained('tbl_chat_messages')->cascadeOnDelete();
            $table->enum('sender', ['user', 'assistant']);
            $table->text('message');
            $table->string('message_type', 20)->default('text');
            $table->string('intent', 30)->nullable()->index();
            $table->string('ai_model', 100)->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['chat_session_id', 'request_uuid']);
            $table->index(['chat_session_id', 'created_at', 'id'], 'chat_history_order');
            $table->index('created_at');
        });

        Schema::create('tbl_chat_product_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained('tbl_chat_messages')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('tbl_products')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['chat_message_id', 'position']);
        });

        Schema::create('tbl_chat_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained('tbl_chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_message_id')->unique()->constrained('tbl_chat_messages')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 180);
            $table->string('phone', 10);
            $table->text('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_chat_contacts');
        Schema::dropIfExists('tbl_chat_product_results');
        Schema::dropIfExists('tbl_chat_messages');
        Schema::dropIfExists('tbl_chat_sessions');
    }
};
