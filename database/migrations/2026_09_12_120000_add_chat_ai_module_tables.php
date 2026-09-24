<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the chat module needs to answer from the catalogue and to be taken over
 * by a person.
 *
 * Three additions. Sessions learn a reply mode and a small conversation
 * context, so "tell me more" has something to be more about. Messages learn
 * who actually wrote them - the guest, the model, or a named admin - which
 * `sender` cannot say, because a manager's reply is still the assistant side
 * of the conversation as far as the visitor is concerned.
 *
 * And tbl_chat_embeddings, which is the retrieval index: one row per indexable
 * thing in the store with the vector that was computed for it. The vector is
 * JSON rather than a MySQL 9 VECTOR column because the similarity is computed
 * in PHP either way - community MySQL has the type but not a cosine distance
 * function - and JSON keeps the schema working on SQLite, which is what the
 * test suite runs on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_chat_sessions', function (Blueprint $table) {
            // 'ai' or 'manual'. Manual means a person has taken the
            // conversation over and the model must stay out of it.
            $table->string('reply_mode', 10)->default('ai')->after('owner_hash');

            // What the conversation is currently about - the product being
            // discussed and how much of its description has been read out, so
            // a follow-up continues rather than repeats.
            $table->json('context')->nullable()->after('reply_mode');

            $table->timestamp('last_message_at')->nullable()->after('context');

            // Visitor messages nobody in the panel has read yet.
            $table->unsignedInteger('unread_count')->default(0)->after('last_message_at');

            $table->index('reply_mode');
            $table->index('last_message_at');
        });

        Schema::table('tbl_chat_messages', function (Blueprint $table) {
            // 'guest', 'ai' or 'manager'. `sender` stays the side of the
            // conversation; this is the author.
            $table->string('authored_by', 10)->default('ai')->after('sender');

            $table->foreignId('admin_id')->nullable()->after('authored_by')
                ->constrained('tbl_admins')->nullOnDelete();
        });

        // Existing rows predate the column and its default is wrong for half
        // of them.
        DB::table('tbl_chat_messages')->where('sender', 'user')->update(['authored_by' => 'guest']);

        Schema::table('tbl_chat_sessions', function (Blueprint $table) {
            $table->index(['unread_count', 'last_message_at'], 'chat_inbox_order');
        });

        Schema::create('tbl_chat_embeddings', function (Blueprint $table) {
            $table->id();

            // 'product', 'category', 'faq', 'page' or 'post'.
            $table->string('source_type', 20);

            // Unique within its type: a product id, or an FAQ row's position.
            $table->string('source_key', 120);

            // Set for the types that are a model, so a deleted product's index
            // row can be found and removed.
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('title', 200);
            $table->text('body');
            $table->string('url', 255)->nullable();

            // sha256 of the body. Re-indexing skips anything unchanged rather
            // than paying for an embedding call per record per run.
            $table->char('checksum', 64);

            $table->string('model', 100);
            $table->unsignedSmallInteger('dimensions');
            $table->json('vector');
            $table->timestamps();

            $table->unique(['source_type', 'source_key']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_chat_embeddings');

        Schema::table('tbl_chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
            $table->dropColumn('authored_by');
        });

        Schema::table('tbl_chat_sessions', function (Blueprint $table) {
            $table->dropIndex('chat_inbox_order');
            $table->dropIndex(['reply_mode']);
            $table->dropIndex(['last_message_at']);
            $table->dropColumn(['reply_mode', 'context', 'last_message_at', 'unread_count']);
        });
    }
};
