<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration modifies the language_models table to support multiple providers
     * with the same model_id by:
     * 1. Adding a unique system_id (UUID) field as primary identifier
     * 2. Removing unique constraint from model_id to allow duplicates across providers
     * 3. Adding composite index for model_id + provider_id for efficient queries
     */
    public function up(): void
    {
        // Add system_id column if it doesn't exist
        if (!Schema::hasColumn('language_models', 'system_id')) {
            Schema::table('language_models', function (Blueprint $table) {
                $table->uuid('system_id')->nullable()->after('id');
            });
        }
        
        // Generate UUIDs for all existing records
        DB::table('language_models')->whereNull('system_id')->get()->each(function ($model) {
            DB::table('language_models')
                ->where('id', $model->id)
                ->update(['system_id' => \Illuminate\Support\Str::uuid()]);
        });
        
        // Remove unique constraint on model_id using raw SQL for reliability
        $indexes = DB::select("SHOW INDEX FROM language_models WHERE Key_name LIKE '%model_id%' AND Non_unique = 0");
        foreach ($indexes as $index) {
            try {
                DB::statement("ALTER TABLE language_models DROP INDEX `{$index->Key_name}`");
            } catch (\Exception $e) {
                // Index might already be dropped
            }
        }
        
        // Add composite index for efficient model_id + provider_id queries
        try {
            DB::statement("CREATE INDEX language_models_model_provider_index ON language_models (model_id, provider_id)");
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        // Add unique constraint on system_id
        try {
            DB::statement("ALTER TABLE language_models ADD UNIQUE KEY language_models_system_id_unique (system_id)");
        } catch (\Exception $e) {
            // Constraint might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the composite index
        try {
            DB::statement("DROP INDEX language_models_model_provider_index ON language_models");
        } catch (\Exception $e) {
            // Index might not exist
        }
        
        // Re-add unique constraint to model_id (note: this may fail if duplicates exist)
        try {
            DB::statement("ALTER TABLE language_models ADD UNIQUE KEY language_models_model_id_unique (model_id)");
        } catch (\Exception $e) {
            // Constraint might already exist or duplicates prevent creation
        }
        
        // Remove system_id column
        if (Schema::hasColumn('language_models', 'system_id')) {
            Schema::table('language_models', function (Blueprint $table) {
                $table->dropColumn('system_id');
            });
        }
    }
};
