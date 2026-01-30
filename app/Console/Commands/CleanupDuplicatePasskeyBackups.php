<?php

namespace App\Console\Commands;

use App\Models\PasskeyBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicatePasskeyBackups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passkey:cleanup-duplicates {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate passkey backups, keeping only the most recent backup for each user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('Searching for duplicate passkey backups...');

        // Find usernames with multiple backups
        $duplicates = PasskeyBackup::select('username', DB::raw('COUNT(*) as count'))
            ->groupBy('username')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('✅ No duplicate passkey backups found.');

            return Command::SUCCESS;
        }

        $this->warn("Found {$duplicates->count()} users with duplicate backups:");

        $totalDeleted = 0;

        foreach ($duplicates as $duplicate) {
            $username = $duplicate->username;
            $count = $duplicate->count;

            // Get all backups for this user, ordered by updated_at (newest first)
            $backups = PasskeyBackup::where('username', $username)
                ->orderBy('updated_at', 'desc')
                ->get();

            // Keep the first one (newest), delete the rest
            $toKeep = $backups->first();
            $toDelete = $backups->slice(1);

            $this->line("  📌 {$username}: {$count} backups found");
            $this->line("     ✅ Keeping: ID {$toKeep->id} (updated: {$toKeep->updated_at})");

            foreach ($toDelete as $backup) {
                $this->line("     🗑️  Deleting: ID {$backup->id} (updated: {$backup->updated_at})");

                if (! $dryRun) {
                    $backup->delete();
                }
                $totalDeleted++;
            }
        }

        if ($dryRun) {
            $this->info("\n📊 Summary (DRY RUN):");
            $this->info("   Would delete {$totalDeleted} duplicate backups");
            $this->info('   Run without --dry-run to apply changes');
        } else {
            $this->info("\n✅ Cleanup complete:");
            $this->info("   Deleted {$totalDeleted} duplicate backups");
        }

        return Command::SUCCESS;
    }
}
