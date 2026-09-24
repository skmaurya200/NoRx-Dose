<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The lines of an order.
     *
     * Name, SKU and unit price are copied onto the row rather than read back
     * through the product: a receipt has to keep saying what was actually
     * bought and what it actually cost, however the catalogue changes later.
     */
    public function up(): void
    {
        Schema::create('tbl_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('tbl_orders')
                ->cascadeOnDelete();

            // Nullable: a product can be removed from the catalogue while its
            // orders stay readable. The snapshot below is what a receipt uses.
            $table->foreignId('product_id')->nullable()
                ->constrained('tbl_products')
                ->nullOnDelete();

            $table->foreignId('pack_id')->nullable()
                ->constrained('tbl_product_packs')
                ->nullOnDelete();

            $table->string('name', 200);
            $table->string('pack_label', 60)->nullable();
            $table->string('sku', 60)->nullable();

            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_order_items');
    }
};
