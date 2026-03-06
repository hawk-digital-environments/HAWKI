<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds unique_name (lowercase, stable identifier) to api_providers.
     * provider_name stays as the display name.
     */
    public function up(): void
    {
        // Step 1: Add unique_name column
        Schema::table('api_providers', function (Blueprint $table) {
            $table->string('unique_name')->nullable()->after('id');
        });

        // Step 2: Generate unique_name from provider_name for all providers
        $providers = DB::table('api_providers')->get();
        foreach ($providers as $provider) {
            $uniqueName = Str::slug($provider->provider_name);
            
            // Ensure uniqueness
            $suffix = 1;
            $baseUniqueName = $uniqueName;
            while (DB::table('api_providers')->where('unique_name', $uniqueName)->where('id', '!=', $provider->id)->exists()) {
                $uniqueName = $baseUniqueName . '-' . $suffix;
                $suffix++;
            }
            
            DB::table('api_providers')
                ->where('id', $provider->id)
                ->update(['unique_name' => $uniqueName]);
        }

        // Step 3: Make unique_name not nullable and unique
        Schema::table('api_providers', function (Blueprint $table) {
            $table->string('unique_name')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_providers', function (Blueprint $table) {
            $table->dropUnique(['unique_name']);
            $table->dropColumn('unique_name');
        });
    }
};
