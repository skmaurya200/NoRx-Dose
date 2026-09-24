<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Username becomes the primary sign-in identifier. Nullable so the accounts
     * that already exist keep working - they still sign in by email or phone
     * until an administrator gives them a username.
     */
    public function up(): void
    {
        Schema::table('tbl_admins', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');

            // The user list filters on it.
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_admins', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
