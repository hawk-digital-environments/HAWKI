<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Orchid\Platform\Models\Role;
use Illuminate\Support\Facades\DB;

class SyncUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:sync-roles 
                           {--dry-run : Show what would be changed without making changes}
                           {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Orchid roles for all users based on their employeetype';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🔄 Starting User-Role Synchronization');
        $this->newLine();

        // Get all users with employeetype (excluding system users)
        $users = User::whereNotNull('employeetype')
                    ->whereNotIn('username', ['HAWKI']) // Exclude system users
                    ->get();
        
        if ($users->isEmpty()) {
            $this->info('No users with employeetype found.');
            return 0;
        }

        $this->info("Found {$users->count()} users with employeetype");
        $this->newLine();

        // Map employeetype to role slugs
        $employeetypeMapping = $this->getEmployeeTypeMapping();

        $changes = [];
        $errors = [];

        foreach ($users as $user) {
            $result = $this->analyzeUserRoleSync($user, $employeetypeMapping);
            
            if ($result['hasChanges']) {
                $changes[] = $result;
            }
            
            if ($result['error']) {
                $errors[] = $result;
            }
        }

        // Show what will be changed
        $this->displayChanges($changes, $errors);

        if (empty($changes)) {
            $this->info('✅ All users already have the correct Orchid roles assigned.');
            return 0;
        }

        if ($dryRun) {
            $this->info('🔍 Dry run completed. Use --force to apply changes.');
            return 0;
        }

        // Confirm changes
        if (!$force && !$this->confirm('Do you want to apply these changes?')) {
            $this->info('Operation cancelled.');
            return 1;
        }

        // Apply changes
        $this->applyChanges($changes);

        $this->info('✅ User role synchronization completed.');
        return 0;
    }

    /**
     * Get the mapping between employeetype and role slugs dynamically
     */
    private function getEmployeeTypeMapping(): array
    {
        $mapping = [];
        
        // Get all available Orchid roles
        $roles = Role::all();
        
        foreach ($roles as $role) {
            // Map slug to slug (exact match)
            $mapping[$role->slug] = $role->slug;
            
            // Map name to slug (case variations)
            $mapping[$role->name] = $role->slug;
            $mapping[strtolower($role->name)] = $role->slug;
            $mapping[ucfirst(strtolower($role->name))] = $role->slug;
        }
        
        return $mapping;
    }

    /**
     * Analyze what changes would be made for a user
     */
    private function analyzeUserRoleSync(User $user, array $mapping): array
    {
        $employeetype = trim($user->employeetype);
        $targetRoleSlug = $mapping[$employeetype] ?? null;
        
        $result = [
            'user' => $user,
            'employeetype' => $employeetype,
            'targetRoleSlug' => $targetRoleSlug,
            'hasChanges' => false,
            'error' => null,
        ];

        if (!$targetRoleSlug) {
            $result['error'] = "Unknown employeetype: {$employeetype}";
            return $result;
        }

        $targetRole = Role::where('slug', $targetRoleSlug)->first();
        if (!$targetRole) {
            $result['error'] = "Orchid role not found for slug: {$targetRoleSlug}";
            return $result;
        }

        $currentRoles = $user->roles;
        $result['currentRoles'] = $currentRoles;
        $result['targetRole'] = $targetRole;

        // Check if user has the required role from employeetype
        $hasRequiredRole = $currentRoles->contains('id', $targetRole->id);
        
        if (!$hasRequiredRole) {
            $result['hasChanges'] = true;
        }

        return $result;
    }

    /**
     * Display the changes that will be made
     */
    private function displayChanges(array $changes, array $errors): void
    {
        if (!empty($errors)) {
            $this->error('❌ Errors found:');
            foreach ($errors as $error) {
                $this->line("  - User: {$error['user']->name} (ID: {$error['user']->id}) - {$error['error']}");
            }
            $this->newLine();
        }

        if (!empty($changes)) {
            $this->info('📋 Changes to be made:');
            foreach ($changes as $change) {
                $user = $change['user'];
                $currentRoleNames = $change['currentRoles']->pluck('name')->join(', ') ?: 'None';
                $targetRoleName = $change['targetRole']->name;
                
                $this->line("  - User: {$user->name} (ID: {$user->id})");
                $this->line("    employeetype: {$change['employeetype']}");
                $this->line("    Current roles: {$currentRoleNames}");
                $this->line("    Will add role: {$targetRoleName}");
                $this->newLine();
            }
        }
    }

    /**
     * Apply the role changes
     */
    private function applyChanges(array $changes): void
    {
        $this->info('🔄 Applying changes...');
        
        $successCount = 0;
        $errorCount = 0;

        foreach ($changes as $change) {
            try {
                $user = $change['user'];
                $targetRole = $change['targetRole'];
                
                // Add required role (never remove any roles)
                $user->roles()->attach($targetRole->id);
                
                $this->line("✅ {$user->name}: added role '{$targetRole->name}'");
                $successCount++;
                
            } catch (\Exception $e) {
                $this->error("❌ Failed to update {$change['user']->name}: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("📊 Summary: {$successCount} successful, {$errorCount} errors");
    }
}
