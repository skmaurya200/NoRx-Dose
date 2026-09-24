<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every browser session this account signed in before this moment is over.
     * Set by an Admin's "sign out everywhere"; checked on each panel request.
     */
    public function up(): void
    {
        Schema::table('tbl_admins', function (Blueprint $table) {
            $table->timestamp('sessions_revoked_at')->nullable()->after('password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_admins', function (Blueprint $table) {
            $table->dropColumn('sessions_revoked_at');
        });
    }
};
