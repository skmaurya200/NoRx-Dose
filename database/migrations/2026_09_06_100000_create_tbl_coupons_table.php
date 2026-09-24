<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discount codes, managed from the panel rather than hard-coded in the
     * cart script. Everything the storefront needs to decide whether a code
     * applies lives on the row, so the rule is stated once and enforced
     * server-side on every order.
     */
    public function up(): void
    {
        Schema::create('tbl_coupons', function (Blueprint $table) {
            $table->id();

            // Stored upper-cased so "save10" and "SAVE10" cannot both exist.
            $table->string('code', 40)->unique();

            // What the customer sees on the cart card, e.g. "10% off your
            // first order". Falls back to a generated line when left empty.
            $table->string('description', 160)->nullable();

            $table->enum('type', ['percent', 'fixed'])->default('percent');

            // A percentage (0-100) or a flat amount, depending on the type.
            $table->decimal('value', 10, 2);

            // The condition the operator asked for: spend this much before the
            // code applies. Zero means no minimum.
            $table->decimal('min_order_amount', 10, 2)->default(0);

            // Optional ceiling for a percentage code, so "20% off" cannot take
            // $400 off a large order.
            $table->decimal('max_discount_amount', 10, 2)->nullable();

            // Null means unlimited. used_count is incremented as orders are
            // placed, inside the same transaction as the order itself.
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('is_active')->default(true);

            // Listed on the cart and checkout as a one-tap offer. A private
            // code still works, it just is not advertised.
            $table->boolean('is_public')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // The storefront asks for "public, active, in date" on every cart
            // view, so that combination is worth an index.
            $table->index(['is_active', 'is_public', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_coupons');
    }
};
