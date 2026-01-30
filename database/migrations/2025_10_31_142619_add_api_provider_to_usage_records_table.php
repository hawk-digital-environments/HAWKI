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
        Schema::table('usage_records', function (Blueprint $table) {
            // Add api_provider column before model column
            $table->string('api_provider')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropColumn('api_provider');
        });
    }
};
