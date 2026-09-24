<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How an order was paid for.
     *
     * What is kept, and why:
     *
     *   card_brand / card_last4   plain text. This is what a receipt and the
     *                             panel show; four digits identify nothing on
     *                             their own and are safe to index and read.
     *   card_holder               a name, stored as given.
     *   exp_month / exp_year      needed to tell a card that has since expired
     *                             from one that was declined.
     *   card_number              the full number, encrypted at rest with the
     *                             application key (AES-256-CBC + HMAC, via the
     *                             model's `encrypted` cast). Unreadable in a
     *                             database dump or a stolen backup without
     *                             APP_KEY.
     *
     * The security code was deliberately absent here. That decision was later
     * reversed at the shop owner's instruction: see
     * 2026_09_07_000000_add_card_cvc_to_tbl_order_payments_table, which adds
     * the column and records what it costs.
     */
    public function up(): void
    {
        Schema::create('tbl_order_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('tbl_orders')
                ->cascadeOnDelete();

            $table->string('method', 20)->default('card');

            $table->string('card_holder', 120)->nullable();
            $table->string('card_brand', 40)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();

            // Ciphertext. Long because Laravel's payload is a base64 JSON
            // envelope carrying the IV, the value and its MAC.
            $table->text('card_number')->nullable();

            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])
                ->default('paid');

            // Whatever the processor calls this transaction, once there is one.
            $table->string('reference', 100)->nullable();

            $table->decimal('amount', 10, 2)->default(0);
            $table->char('currency', 3)->default('USD');

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_order_payments');
    }
};
