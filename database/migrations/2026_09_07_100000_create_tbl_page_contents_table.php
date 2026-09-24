<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editable copy for the storefront's fixed pages.
     *
     * One narrow table rather than a page/section/field tree, because the
     * shape of each page is decided by its template, not by an editor. What
     * fields exist, what they are called and what they fall back to lives in
     * App\Support\Content\PageSchema; this table only remembers the values an
     * operator has actually changed.
     *
     * The consequence worth naming: a page with no rows here renders exactly
     * as it was written, because every field falls back to the text that was
     * hard-coded in the template. Editing is additive, so nothing can be
     * accidentally blanked by an empty table.
     */
    public function up(): void
    {
        Schema::create('tbl_page_contents', function (Blueprint $table) {
            $table->id();

            // e.g. "home", "about" - matches a key in PageSchema.
            $table->string('page_key', 60);

            // e.g. "hero", "about"
            $table->string('section_key', 60);

            // e.g. "title", "background"
            $table->string('field_key', 60);

            // Long text for a paragraph, a heading with inline markup, or the
            // public path of an uploaded image.
            $table->text('value')->nullable();

            $table->timestamps();

            // One value per field, and the lookup the storefront makes on
            // every page load is by page alone.
            $table->unique(['page_key', 'section_key', 'field_key'], 'page_contents_field_unique');
            $table->index('page_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_page_contents');
    }
};
