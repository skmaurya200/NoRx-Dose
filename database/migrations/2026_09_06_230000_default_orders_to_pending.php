<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order arrives unpaid.
     *
     * The first pass wrote every order as paid the moment it was placed, which
     * was only true because there is no payment processor wired up yet. That
     * made the panel's payment column meaningless - everything said "paid",
     * including orders nobody had taken money for.
     *
     * Now an order lands as pending on both counts and an operator moves it
     * on once the money is actually in.
     */
    public function up(): void
    {
        Schema::table('tbl_orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])
                ->default('pending')
                ->change();

            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])
                ->default('pending')
                ->change();

            // Cancelling an order puts its stock back. Stamped so it happens
            // exactly once however many times the status is flipped after.
            $table->timestamp('stock_restored_at')->nullable()->after('placed_at');
        });

        Schema::table('tbl_order_payments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])
                ->default('processing')
                ->change();

            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])
                ->default('paid')
                ->change();

            $table->dropColumn('stock_restored_at');
        });

        Schema::table('tbl_order_payments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])
                ->default('paid')
                ->change();
        });
    }
};
