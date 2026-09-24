<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detail sections an operator adds themselves.
 *
 * The fixed columns - benefits, how to use, warnings, ingredients, storage -
 * cover what most products need and nothing more. A tincture wants a dilution
 * table, a device wants what is in the box, and neither is worth a column of
 * its own that every other product leaves null.
 *
 * JSON rather than a table because these rows are only ever read back with the
 * product, in the order they were written, and never queried across products.
 * Same reasoning as the specifications column beside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_product_details', function (Blueprint $table) {
            $table->json('extra_sections')->nullable()->after('specifications');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_product_details', function (Blueprint $table) {
            $table->dropColumn('extra_sections');
        });
    }
};
