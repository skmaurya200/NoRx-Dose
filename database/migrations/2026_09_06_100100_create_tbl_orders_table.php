<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One placed order.
     *
     * Every money column is written from figures the server recomputed out of
     * the catalogue - the browser's numbers are only ever a suggestion. The
     * billing address and the coupon are snapshotted onto the row so an order
     * still reads correctly after the customer moves house or the code is
     * deleted.
     */
    public function up(): void
    {
        Schema::create('tbl_orders', function (Blueprint $table) {
            $table->id();

            // Shown to the customer and used in support, e.g. "AW-8FQ2M7KD".
            $table->string('order_number', 32)->unique();

            // The confirmation page is addressed by this rather than by id, so
            // a stranger cannot walk /order/1, /order/2 and read addresses.
            $table->string('public_token', 64)->unique();

            $table->enum('status', [
                'pending', 'processing', 'shipped', 'delivered', 'cancelled',
            ])->default('processing');

            $table->enum('payment_status', [
                'pending', 'paid', 'failed', 'refunded',
            ])->default('paid');

            /* ------------------------------------------------- the customer */

            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 180);
            $table->string('phone', 32);

            /* -------------------------------------------- billing address */

            $table->string('street', 200);
            $table->string('city', 100);
            $table->char('state', 2);
            $table->string('postal_code', 10);
            $table->char('country', 2)->default('US');

            $table->string('notes', 1000)->nullable();

            /* ---------------------------------------------------- shipping */

            $table->string('shipping_method', 40);
            $table->string('shipping_method_label', 120);

            /* ------------------------------------------------------ coupon */

            // Nulled rather than cascaded: deleting a code must not delete the
            // orders that used it, and the snapshot below keeps the receipt
            // readable either way.
            $table->foreignId('coupon_id')->nullable()
                ->constrained('tbl_coupons')
                ->nullOnDelete();

            $table->string('coupon_code', 40)->nullable();
            $table->string('coupon_description', 160)->nullable();

            /* ------------------------------------------------------ totals */

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->char('currency', 3)->default('USD');

            /* ------------------------------------------------------- audit */

            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('placed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The panel list is "newest first", optionally filtered by status.
            $table->index(['status', 'placed_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_orders');
    }
};
