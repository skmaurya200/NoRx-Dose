<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The SEO fields the first pass left out.
     *
     * cover_alt matters more than it looks: the cover is the largest image on
     * the page and the one used as the share preview, and an image with no
     * alternative text is invisible to a screen reader and to image search.
     */
    public function up(): void
    {
        Schema::table('tbl_blog_posts', function (Blueprint $table) {
            $table->string('cover_alt', 200)->nullable()->after('cover_path');

            // Kept out of the sitemap and marked noindex. Some posts exist to
            // be linked to from an email or an ad rather than to be found.
            $table->boolean('is_indexable')->default(true)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_blog_posts', function (Blueprint $table) {
            $table->dropColumn(['cover_alt', 'is_indexable']);
        });
    }
};
