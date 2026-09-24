<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide settings: the brand name, the logo, the contact details in the
 * footer, the social links.
 *
 * Deliberately sparse and key-value rather than one wide row. Only a setting
 * an operator has actually changed gets a row; everything else falls back to
 * the default recorded in App\Support\Settings\SettingSchema, which means the
 * site ships working and a cleared field restores the original rather than
 * leaving a hole.
 *
 * Same shape as tbl_page_contents on purpose - the two modules share their
 * form partials, and a matching table is what makes that honest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_settings', function (Blueprint $table) {
            $table->id();

            // "brand", "contact", "social" - the tab it is edited under.
            $table->string('group_key', 60);
            $table->string('item_key', 60);

            // Text, because a setting may be a paragraph (the footer blurb) or
            // a path (an uploaded logo). Nothing here is large enough to need
            // anything wider.
            $table->text('value')->nullable();

            $table->timestamps();

            $table->unique(['group_key', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_settings');
    }
};
