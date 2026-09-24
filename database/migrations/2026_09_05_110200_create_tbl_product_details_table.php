<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_product_details', function (Blueprint $table) {
            $table->id();

            // One detail row per product. Unique, so a duplicate can never be
            // created by a double-submit, and cascade because the row is
            // meaningless without its product.
            $table->foreignId('product_id')->unique()
                ->constrained('tbl_products')
                ->cascadeOnDelete();

            $table->text('ingredients')->nullable();
            $table->text('benefits')->nullable();
            $table->text('how_to_use')->nullable();
            $table->text('storage')->nullable();
            $table->text('warnings')->nullable();

            $table->string('country_of_origin', 100)->nullable();
            $table->string('manufacturer', 180)->nullable();
            $table->unsignedSmallInteger('shelf_life_months')->nullable();
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_gluten_free')->default(false);

            // Free-form spec rows the panel edits as a repeater. JSON keeps the
            // schema stable while every wellness line has its own vocabulary.
            $table->json('specifications')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_product_details');
    }
};
