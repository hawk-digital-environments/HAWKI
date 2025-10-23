<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Add target_roles column to store role slugs (replaces target_users functionality)
            $table->json('target_roles')->nullable()->after('is_global');
            // Drop old target_users column
            $table->dropColumn('target_users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Restore target_users column
            $table->json('target_users')->nullable()->after('is_global');
            // Drop target_roles column
            $table->dropColumn('target_roles');
        });
    }
};
