<?php

namespace App\Console\Commands;

use App\Http\Controllers\AppCssController;
use App\Models\AppCss;
use Illuminate\Console\Command;

class CleanupCssEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'css:cleanup 
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all CSS entries except custom-styles from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Starting CSS cleanup...');
        $this->newLine();

        // Get all CSS entries except custom-styles
        $entriesToDelete = AppCss::where('name', '!=', 'custom-styles')->get();

        if ($entriesToDelete->isEmpty()) {
            $this->info('✓ No CSS entries to clean up. Only custom-styles exists.');
            return Command::SUCCESS;
        }

        $this->info('Found ' . $entriesToDelete->count() . ' CSS entries to remove:');
        $this->newLine();

        // Display table of entries to be deleted
        $tableData = [];
        foreach ($entriesToDelete as $entry) {
            $tableData[] = [
                'name' => $entry->name,
                'description' => substr($entry->description, 0, 50) . '...',
                'active' => $entry->active ? 'Yes' : 'No',
                'updated_at' => $entry->updated_at->format('Y-m-d H:i:s'),
            ];
        }

        $this->table(
            ['Name', 'Description', 'Active', 'Last Updated'],
            $tableData
        );

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes were made');
            $this->info('Run without --dry-run to actually delete these entries');
            return Command::SUCCESS;
        }

        // Confirm deletion
        if (!$this->confirm('Do you want to delete these ' . $entriesToDelete->count() . ' CSS entries?', true)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Delete entries
        $deletedCount = 0;
        foreach ($entriesToDelete as $entry) {
            $this->info("Deleting: {$entry->name}");
            $entry->delete();
            $deletedCount++;
        }

        // Clear CSS cache
        AppCssController::clearCaches();
        
        $this->newLine();
        $this->info("✓ Successfully deleted {$deletedCount} CSS entries");
        $this->info('✓ CSS cache cleared');
        $this->newLine();
        $this->info('Only custom-styles.css remains in the database.');

        return Command::SUCCESS;
    }
}
