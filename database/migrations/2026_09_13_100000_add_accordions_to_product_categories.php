<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expandable sections an operator writes for one category.
 *
 * A category page carries the buying guidance a product page cannot: how to
 * choose between the things in it, what the differences mean, what to read
 * first. That is several headings with a few paragraphs each, which is an
 * accordion rather than one long description.
 *
 * JSON for the same reason as the product's own sections beside it: the rows
 * are only ever read back with their category, in the order they were written,
 * and never queried across categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_product_categories', function (Blueprint $table) {
            $table->json('accordions')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_product_categories', function (Blueprint $table) {
            $table->dropColumn('accordions');
        });
    }
};
