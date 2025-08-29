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
        Schema::table('provider_settings', function (Blueprint $table) {
            // Remove the old URL fields and api_format field since they are now managed through API format relationships
            $table->dropColumn(['api_format', 'base_url', 'ping_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_settings', function (Blueprint $table) {
            // Add back the old fields for rollback
            $table->string('api_format')->nullable()->after('provider_name');
            $table->string('base_url')->nullable()->after('api_key');
            $table->string('ping_url')->nullable()->after('base_url');
        });
    }
};
