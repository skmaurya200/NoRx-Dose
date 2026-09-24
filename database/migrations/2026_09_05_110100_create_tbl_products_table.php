<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_products', function (Blueprint $table) {
            $table->id();

            // A category cannot be deleted while products still point at it -
            // the service turns that into a readable 409 instead of a fatal.
            $table->foreignId('category_id')
                ->constrained('tbl_product_categories')
                ->restrictOnDelete();

            $table->string('name', 180);
            $table->string('slug', 200)->unique();
            $table->string('sku', 60)->unique();
            $table->string('brand', 120)->nullable();

            $table->string('short_description', 400)->nullable();
            $table->longText('description')->nullable();

            // Money is decimal, never float: 0.1 + 0.2 is not 0.3 in binary
            // floating point and a cart total would drift.
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->char('currency', 3)->default('USD');

            $table->string('unit', 40)->nullable();          // "60 capsules", "250 g"
            $table->decimal('weight_grams', 8, 2)->nullable();

            $table->boolean('track_inventory')->default(true);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('allow_backorder')->default(false);

            // Path relative to public/.
            $table->string('thumbnail_path', 255)->nullable();

            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);

            // Denormalised so a storefront listing never has to aggregate the
            // reviews table. Written by the reviews module, not by hand.
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);

            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 255)->nullable();

            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
            $table->index(['category_id', 'status']);
            $table->index('is_featured');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_products');
    }
};
