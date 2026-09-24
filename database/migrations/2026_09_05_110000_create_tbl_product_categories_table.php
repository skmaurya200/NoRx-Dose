<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_product_categories', function (Blueprint $table) {
            $table->id();

            // Self-reference for sub-categories. restrictOnDelete rather than
            // cascade: silently wiping a whole branch because someone removed
            // the parent is how catalogues lose data.
            $table->foreignId('parent_id')->nullable()
                ->constrained('tbl_product_categories')
                ->nullOnDelete();

            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();

            // Path relative to public/, e.g. uploads/categories/ab12.webp.
            // Uploads are served from public/, never the storage disk.
            $table->string('image_path', 255)->nullable();

            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 255)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Drives the storefront listing and the panel's default ordering.
            $table->index(['is_active', 'deleted_at']);
            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_product_categories');
    }
};
