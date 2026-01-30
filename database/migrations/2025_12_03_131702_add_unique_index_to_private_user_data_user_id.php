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
        // Remove duplicate entries, keeping only the one with the highest ID for each user_id
        // (which typically represents the most recent entry)
        DB::statement('
            DELETE t1 FROM private_user_data t1
            INNER JOIN (
                SELECT user_id, MAX(id) as max_id
                FROM private_user_data
                GROUP BY user_id
                HAVING COUNT(*) > 1
            ) t2 ON t1.user_id = t2.user_id
            WHERE t1.id < t2.max_id
        ');

        // Add unique constraint
        Schema::table('private_user_data', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('private_user_data', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
