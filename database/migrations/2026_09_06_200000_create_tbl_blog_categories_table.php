<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal categories - the chips above the post list.
     *
     * A separate table rather than a string on the post: the storefront filters
     * on them, they are renamed from time to time, and a typo in one post
     * should not create a category of its own.
     */
    public function up(): void
    {
        Schema::create('tbl_blog_categories', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);

            // Derived from the name, never accepted from the request - see
            // App\Services\Catalogue\SlugGenerator.
            $table->string('slug', 150)->unique();

            $table->string('description', 500)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_blog_categories');
    }
};
