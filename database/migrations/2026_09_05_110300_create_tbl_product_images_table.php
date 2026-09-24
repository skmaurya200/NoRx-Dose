<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('tbl_products')
                ->cascadeOnDelete();

            // Path relative to public/, e.g. uploads/products/ab12.webp.
            $table->string('image_path', 255);
            $table->string('alt_text', 180)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_product_images');
    }
};
