<?php

namespace App\Console\Commands;

use App\Models\AppSystemText;
use App\Services\TextImport\TextImportService;
use Illuminate\Console\Command;

class SyncSystemTextKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system-text:sync
                          {--force : Force update of existing keys}
                          {--dry-run : Preview changes without modifying database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize system text keys from JSON files to database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $forceUpdate = $this->option('force');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made to the database');
            $this->newLine();
        }
        
        $this->info('Synchronizing system text keys...');
        $this->newLine();
        
        try {
            $textImportService = new TextImportService;
            
            // Get count before import
            $beforeCount = AppSystemText::count();
            $this->info("Current entries in database: {$beforeCount}");
            
            if ($dryRun) {
                // Perform dry-run analysis
                $analysis = $textImportService->analyzeSystemTexts($forceUpdate);
                
                $this->newLine();
                $this->info('📊 Analysis Results:');
                $this->table(
                    ['Language', 'New Keys', 'Existing Keys', 'Would Update'],
                    collect($analysis['languages'])->map(fn($lang, $key) => [
                        $key,
                        $lang['new'],
                        $lang['existing'],
                        $forceUpdate ? $lang['existing'] : 0
                    ])
                );
                
                $this->newLine();
                $this->info("Total keys in JSON files: {$analysis['total_keys']}");
                $this->info("New keys to add: {$analysis['new_keys']}");
                
                if ($forceUpdate) {
                    $this->warn("Would update: {$analysis['existing_keys']} existing keys (--force mode)");
                }
                
                $this->newLine();
                $this->comment('💡 Run without --dry-run to apply these changes');
                
                return Command::SUCCESS;
            }
            
            // Import system texts
            $processedCount = $textImportService->importSystemTexts($forceUpdate);
            
            // Get count after import
            $afterCount = AppSystemText::count();
            $newKeysCount = $afterCount - $beforeCount;
            
            $this->newLine();
            
            if ($forceUpdate) {
                $this->info("✓ Processed {$processedCount} translation keys (force update mode)");
            } else {
                if ($newKeysCount > 0) {
                    $this->success("✓ Added {$newKeysCount} new translation keys to database");
                } else {
                    $this->info('✓ All translation keys already present in database');
                }
            }
            
            $this->info("Total entries in database: {$afterCount}");
            
            // Clear caches
            try {
                \App\Http\Controllers\LanguageController::clearCaches();
                \App\Http\Controllers\LocalizationController::clearCaches();
                $this->info('✓ Caches cleared');
            } catch (\Exception $e) {
                $this->warn('⚠ Could not clear caches: ' . $e->getMessage());
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('✗ Error synchronizing system text keys: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
