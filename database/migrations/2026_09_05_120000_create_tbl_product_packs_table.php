<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pack sizes - the "Select size" chooser on the product page.
     *
     * A separate table rather than more columns on tbl_products: the number of
     * sizes varies per product, each carries its own price and its own stock,
     * and an order line has to be able to point at the exact size that sold.
     */
    public function up(): void
    {
        Schema::create('tbl_product_packs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('tbl_products')
                ->cascadeOnDelete();

            // What the customer picks, e.g. "30 count", "60 count".
            $table->string('label', 60);

            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();

            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_best_value')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // The page reads these in display order, always scoped to one product.
            $table->index(['product_id', 'sort_order']);

            // Two identical size labels on one product would be unpickable.
            $table->unique(['product_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_product_packs');
    }
};
