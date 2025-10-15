<?php

namespace App\Console\Commands;

use App\Models\Employeetype;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupDuplicateEmployeetypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employeetype:cleanup-duplicates 
                            {--dry-run : Preview cleanup without making changes}
                            {--remove-local : Remove employeetype entries for local auth users (they use 1:1 mapping)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and cleanup duplicate employeetype entries caused by auth_type bug';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $removeLocal = $this->option('remove-local');

        $this->info('🔍 Scanning for duplicate and unnecessary employeetype entries...');
        $this->newLine();

        $issues = [];

        // Issue 1: Duplicate raw_value entries (same raw_value, different auth_method)
        $duplicates = Employeetype::selectRaw('raw_value, GROUP_CONCAT(id) as ids, GROUP_CONCAT(auth_method) as methods, count(*) as count')
            ->groupBy('raw_value')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $issues['duplicates'] = $duplicates;
        }

        // Issue 2: Local auth employeetype entries (no longer needed - use 1:1 mapping)
        $localEntries = Employeetype::where('auth_method', 'local')->get();

        if ($removeLocal && $localEntries->count() > 0) {
            $issues['local_entries'] = $localEntries;
        }

        // Issue 3: Orphaned employeetype entries (no users with this employeetype + auth_method combo)
        $orphanedEntries = $this->findOrphanedEntries();

        if ($orphanedEntries->count() > 0) {
            $issues['orphaned'] = $orphanedEntries;
        }

        // Display results
        if (empty($issues)) {
            $this->info('✅ No issues found! Employeetype table is clean.');
            return Command::SUCCESS;
        }

        // Display issues
        if (isset($issues['duplicates'])) {
            $this->displayDuplicates($issues['duplicates']);
        }

        if (isset($issues['local_entries'])) {
            $this->displayLocalEntries($issues['local_entries']);
        }

        if (isset($issues['orphaned'])) {
            $this->displayOrphanedEntries($issues['orphaned']);
        }

        // Summary
        $totalIssues = 0;
        if (isset($issues['duplicates'])) {
            // Count total duplicate entries (not just unique raw_values)
            foreach ($issues['duplicates'] as $dup) {
                $totalIssues += $dup->count - 1; // -1 because we keep one
            }
        }
        if (isset($issues['local_entries'])) {
            $totalIssues += $issues['local_entries']->count();
        }
        if (isset($issues['orphaned'])) {
            $totalIssues += $issues['orphaned']->count();
        }

        $this->newLine();
        $this->warn("⚠️  Found {$totalIssues} employeetype entries that can be cleaned up");

        // Cleanup mode
        if ($isDryRun) {
            $this->newLine();
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->info('To apply cleanup, run without --dry-run flag');
        } else {
            $this->newLine();
            if ($this->confirm('Do you want to proceed with cleanup?', false)) {
                $this->performCleanup($issues);
            } else {
                $this->info('Cleanup cancelled.');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Find orphaned employeetype entries
     */
    private function findOrphanedEntries()
    {
        $orphaned = collect();

        // Get all employeetypes
        $employeetypes = Employeetype::all();

        foreach ($employeetypes as $employeetype) {
            // Check if any users exist with this employeetype + auth_method combination
            $userCount = User::where('employeetype', $employeetype->raw_value)
                ->where('auth_type', $employeetype->auth_method)
                ->where('isRemoved', false)
                ->count();

            if ($userCount === 0) {
                $orphaned->push($employeetype);
            }
        }

        return $orphaned;
    }

    /**
     * Display duplicate entries
     */
    private function displayDuplicates($duplicates): void
    {
        $this->line('<fg=red>🔴 Duplicate raw_value entries (same raw_value, different auth_method)</>');
        $this->line("   Found: {$duplicates->count()} duplicate raw_values");
        $this->newLine();

        foreach ($duplicates as $duplicate) {
            $this->line("   Raw Value: <fg=yellow>{$duplicate->raw_value}</>");
            $this->line("   Auth Methods: {$duplicate->methods}");
            $this->line("   IDs: {$duplicate->ids}");

            // Show which one is correct based on actual user auth_types
            $ids = explode(',', $duplicate->ids);
            foreach ($ids as $id) {
                $entry = Employeetype::find($id);
                if ($entry) {
                    $userCount = User::where('employeetype', $entry->raw_value)
                        ->where('auth_type', $entry->auth_method)
                        ->where('isRemoved', false)
                        ->count();

                    $status = $userCount > 0 ? "<fg=green>KEEP (has {$userCount} users)</>" : "<fg=red>DELETE (no users)</>";
                    $this->line("      ID {$id} ({$entry->auth_method}): {$status}");
                }
            }
            $this->newLine();
        }
    }

    /**
     * Display local auth entries
     */
    private function displayLocalEntries($entries): void
    {
        $this->line('<fg=yellow>⚠️  Local auth employeetype entries (no longer needed - use 1:1 mapping)</>');
        $this->line("   Found: {$entries->count()} local entries");
        $this->newLine();

        $this->table(
            ['ID', 'Raw Value', 'Display Name', 'Has Users', 'Has Mappings'],
            $entries->map(function ($entry) {
                $userCount = User::where('employeetype', $entry->raw_value)
                    ->where('auth_type', 'local')
                    ->where('isRemoved', false)
                    ->count();

                $mappingCount = $entry->roleAssignments()->count();

                return [
                    $entry->id,
                    $entry->raw_value,
                    $entry->display_name,
                    $userCount > 0 ? "Yes ({$userCount})" : 'No',
                    $mappingCount > 0 ? "Yes ({$mappingCount})" : 'No',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info('ℹ️  Local users now use 1:1 mapping (employeetype = role slug)');
        $this->info('   These entries can be safely deleted.');
    }

    /**
     * Display orphaned entries
     */
    private function displayOrphanedEntries($entries): void
    {
        $this->line('<fg=yellow>⚠️  Orphaned employeetype entries (no users with this combination)</>');
        $this->line("   Found: {$entries->count()} orphaned entries");
        $this->newLine();

        $this->table(
            ['ID', 'Raw Value', 'Auth Method', 'Display Name', 'Has Mappings'],
            $entries->map(function ($entry) {
                $mappingCount = $entry->roleAssignments()->count();

                return [
                    $entry->id,
                    $entry->raw_value,
                    $entry->auth_method,
                    $entry->display_name,
                    $mappingCount > 0 ? "Yes ({$mappingCount})" : 'No',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info('ℹ️  These entries have no active users and can be safely deleted.');
    }

    /**
     * Perform the cleanup
     */
    private function performCleanup(array $issues): void
    {
        $this->newLine();
        $this->warn('🔧 Starting cleanup...');
        $this->newLine();

        $deletedCount = 0;

        // Cleanup duplicates
        if (isset($issues['duplicates'])) {
            $this->line('Processing duplicates...');

            foreach ($issues['duplicates'] as $duplicate) {
                $ids = explode(',', $duplicate->ids);
                $idsToKeep = [];
                $idsToDelete = [];

                // Determine which entries to keep and which to delete
                foreach ($ids as $id) {
                    $entry = Employeetype::find($id);
                    if ($entry) {
                        $userCount = User::where('employeetype', $entry->raw_value)
                            ->where('auth_type', $entry->auth_method)
                            ->where('isRemoved', false)
                            ->count();

                        if ($userCount > 0) {
                            $idsToKeep[] = $id;
                        } else {
                            $idsToDelete[] = $id;
                        }
                    }
                }

                // If all entries have no users, keep the first one
                if (empty($idsToKeep)) {
                    $idsToKeep[] = $ids[0];
                    $idsToDelete = array_slice($ids, 1);
                }

                // Delete unnecessary entries
                foreach ($idsToDelete as $idToDelete) {
                    $entry = Employeetype::find($idToDelete);
                    if ($entry) {
                        $this->line("  ❌ Deleting: ID {$entry->id} ({$entry->raw_value} - {$entry->auth_method})");
                        $entry->delete();
                        $deletedCount++;

                        Log::info('Deleted duplicate employeetype entry', [
                            'id' => $entry->id,
                            'raw_value' => $entry->raw_value,
                            'auth_method' => $entry->auth_method,
                        ]);
                    }
                }

                foreach ($idsToKeep as $idToKeep) {
                    $entry = Employeetype::find($idToKeep);
                    if ($entry) {
                        $this->line("  ✅ Keeping: ID {$entry->id} ({$entry->raw_value} - {$entry->auth_method})");
                    }
                }
            }

            $this->newLine();
        }

        // Cleanup local entries
        if (isset($issues['local_entries'])) {
            $this->line('Processing local auth entries...');

            foreach ($issues['local_entries'] as $entry) {
                $this->line("  ❌ Deleting: ID {$entry->id} ({$entry->raw_value} - local)");
                $entry->delete();
                $deletedCount++;

                Log::info('Deleted local employeetype entry (uses 1:1 mapping now)', [
                    'id' => $entry->id,
                    'raw_value' => $entry->raw_value,
                ]);
            }

            $this->newLine();
        }

        // Cleanup orphaned entries
        if (isset($issues['orphaned'])) {
            $this->line('Processing orphaned entries...');

            foreach ($issues['orphaned'] as $entry) {
                $this->line("  ❌ Deleting: ID {$entry->id} ({$entry->raw_value} - {$entry->auth_method})");
                $entry->delete();
                $deletedCount++;

                Log::info('Deleted orphaned employeetype entry', [
                    'id' => $entry->id,
                    'raw_value' => $entry->raw_value,
                    'auth_method' => $entry->auth_method,
                ]);
            }

            $this->newLine();
        }

        // Summary
        $this->info("✅ Cleanup completed!");
        $this->info("   Deleted: {$deletedCount} employeetype entries");
    }
}
