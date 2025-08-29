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
            $table->foreignId('api_format_id')->nullable()->after('api_format')->constrained('api_formats')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_settings', function (Blueprint $table) {
            $table->dropForeign(['api_format_id']);
            $table->dropColumn('api_format_id');
        });
    }
};
