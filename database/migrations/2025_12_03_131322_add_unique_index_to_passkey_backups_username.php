<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, remove duplicate usernames - keep only the most recent entry for each username
        DB::statement('
            DELETE FROM passkey_backups
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) as id
                    FROM passkey_backups
                    GROUP BY username
                ) as latest
            )
        ');

        Schema::table('passkey_backups', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passkey_backups', function (Blueprint $table) {
            $table->dropUnique(['username']);
        });
    }
};
