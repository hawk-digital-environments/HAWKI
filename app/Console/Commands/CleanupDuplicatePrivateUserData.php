<?php

namespace App\Console\Commands;

use App\Models\PrivateUserData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicatePrivateUserData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'private-user-data:cleanup-duplicates {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate private user data entries, keeping only the most recent entry for each user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('Searching for duplicate private user data entries...');

        // Find user_ids with multiple entries
        $duplicates = PrivateUserData::select('user_id', DB::raw('COUNT(*) as count'))
            ->groupBy('user_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('✅ No duplicate private user data entries found.');

            return Command::SUCCESS;
        }

        $this->warn("Found {$duplicates->count()} users with duplicate private data entries:");

        $totalDeleted = 0;

        foreach ($duplicates as $duplicate) {
            $userId = $duplicate->user_id;
            $count = $duplicate->count;

            // Get all entries for this user, ordered by updated_at (newest first)
            $entries = PrivateUserData::where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->get();

            // Keep the first one (newest), delete the rest
            $toKeep = $entries->first();
            $toDelete = $entries->slice(1);

            $this->line("  📌 User ID {$userId}: {$count} entries found");
            $this->line("     ✅ Keeping: ID {$toKeep->id} (updated: {$toKeep->updated_at})");

            foreach ($toDelete as $entry) {
                $this->line("     🗑️  Deleting: ID {$entry->id} (updated: {$entry->updated_at})");

                if (! $dryRun) {
                    $entry->delete();
                }
                $totalDeleted++;
            }
        }

        if ($dryRun) {
            $this->info("\n📊 Summary (DRY RUN):");
            $this->info("   Would delete {$totalDeleted} duplicate entries");
            $this->info('   Run without --dry-run to apply changes');
        } else {
            $this->info("\n✅ Cleanup complete:");
            $this->info("   Deleted {$totalDeleted} duplicate entries");
        }

        return Command::SUCCESS;
    }
}
