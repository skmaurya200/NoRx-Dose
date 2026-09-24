<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal posts.
     *
     * The body is HTML written in the panel's editor and sanitised on the way
     * in (App\Support\HtmlSanitizer) rather than on the way out, so the column
     * only ever holds markup the storefront is willing to print.
     */
    public function up(): void
    {
        Schema::create('tbl_blog_posts', function (Blueprint $table) {
            $table->id();

            // Nulled rather than cascaded: deleting a category must not take
            // the posts with it. An uncategorised post still reads fine.
            $table->foreignId('category_id')->nullable()
                ->constrained('tbl_blog_categories')
                ->nullOnDelete();

            $table->string('title', 200);
            $table->string('slug', 220)->unique();

            // The card and the meta description fall back to this.
            $table->string('excerpt', 400)->nullable();

            $table->longText('body')->nullable();

            // The "short version" list under the post. A handful of strings
            // with no identity of their own, so JSON rather than a table.
            $table->json('takeaways')->nullable();

            // Path relative to public/.
            $table->string('cover_path', 255)->nullable();

            $table->string('author_name', 120)->default('The Aurum team');

            // Computed from the body on save, but stored so the listing does
            // not have to count words for every card it draws. Overridable by
            // the operator when the estimate reads wrong.
            $table->unsignedSmallInteger('read_minutes')->default(1);

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            // The one post that gets the large treatment at the top of the
            // journal. Only one is used; the newest wins if several are set.
            $table->boolean('is_featured')->default(false);

            $table->unsignedInteger('views_count')->default(0);

            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 255)->nullable();

            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The storefront asks for "published, newest first", often inside
            // one category.
            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_blog_posts');
    }
};
