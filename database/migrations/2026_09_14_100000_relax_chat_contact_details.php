<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A contact detail left in conversation rather than on a form.
 *
 * The form asked for a name, an email, a phone number and a message, and would
 * not accept fewer. The assistant now asks for "an email address or a phone
 * number" in the chat itself, and a visitor who types one of them has given
 * support everything it needs to reply - so the rest have to be optional.
 *
 * The phone column also stops being ten characters. That was an Indian mobile
 * on a form that validated the shape; a number typed into a sentence arrives
 * as "+1 (415) 555-0132" and would have been truncated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_chat_contacts', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->change();
            $table->string('email', 180)->nullable()->change();
            $table->string('phone', 32)->nullable()->change();
            $table->text('message')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_chat_contacts', function (Blueprint $table) {
            $table->string('name', 120)->nullable(false)->change();
            $table->string('email', 180)->nullable(false)->change();
            $table->string('phone', 10)->nullable(false)->change();
            $table->text('message')->nullable(false)->change();
        });
    }
};
