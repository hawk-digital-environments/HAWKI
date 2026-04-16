<?php

use App\Services\TextImport\TextImportService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration ensures that all system text keys from JSON files are present
     * in the database. It will only create missing keys, never update existing ones.
     * This runs automatically during deployment to keep the database in sync.
     */
    public function up(): void
    {
        try {
            // Check if table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('app_system_texts')) {
                Log::warning('Migration: app_system_texts table does not exist yet, skipping sync');
                return;
            }
            
            $textImportService = new TextImportService;
            
            // Get count before import
            $beforeCount = \App\Models\AppSystemText::count();
            
            // Import system texts with forceUpdate = false (only creates new entries)
            $processedCount = $textImportService->importSystemTexts(false);
            
            // Get count after import
            $afterCount = \App\Models\AppSystemText::count();
            $newKeysCount = $afterCount - $beforeCount;
            
            if ($newKeysCount > 0) {
                Log::info("Migration: Synced app_system_text keys - Added {$newKeysCount} new translation keys");
                echo "✓ Added {$newKeysCount} new translation keys to app_system_texts table\n";
            } else {
                Log::info('Migration: app_system_text keys already in sync');
                echo "✓ All translation keys already present in app_system_texts table\n";
            }
        } catch (\Exception $e) {
            Log::error('Migration: Error syncing app_system_text keys: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This migration only adds keys, so there's nothing to reverse.
     * Rolling back would not remove the keys as they might have been modified by users.
     */
    public function down(): void
    {
        // Intentionally left empty - we don't remove keys on rollback
        // as they might have been customized by administrators
        Log::info('Migration rollback: app_system_text keys remain unchanged');
    }
};
