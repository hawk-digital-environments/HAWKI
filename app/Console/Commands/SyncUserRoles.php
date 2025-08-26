<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\DB;

class SyncUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:sync-roles 
                           {user? : Username or ID of specific user to sync}
                           {--dry-run : Show what would be changed without making changes}
                           {--force : Skip confirmation prompt}
                           {--all : Sync roles for all users}
                           {--observer : Use UserObserver logic instead of direct sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Orchid roles for users based on their employeetype or manually trigger UserObserver';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userParam = $this->argument('user');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $syncAll = $this->option('all');
        $useObserver = $this->option('observer');

        // Handle specific user with observer logic
        if ($userParam && $useObserver) {
            return $this->handleUserObserver($userParam);
        }

        // Handle all users with observer logic
        if ($syncAll && $useObserver) {
            return $this->handleAllUsersObserver($force);
        }

        // Original bulk sync logic
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

    /**
     * Handle single user with UserObserver logic
     */
    private function handleUserObserver(string $userParam): int
    {
        $user = $this->findUser($userParam);
        
        if (!$user) {
            $this->error("User not found: {$userParam}");
            return 1;
        }

        $this->info("Triggering UserObserver for user: {$user->username} (ID: {$user->id})");
        $this->info("Employee Type: {$user->employeetype}");
        $this->info("Approval Status: " . ($user->approval ? 'Approved' : 'Pending'));
        
        try {
            $observer = new UserObserver();
            
            // Use reflection to call the private syncOrchidRole method
            $reflection = new \ReflectionClass($observer);
            $method = $reflection->getMethod('syncOrchidRole');
            $method->setAccessible(true);
            $method->invoke($observer, $user);
            
            $this->info("✅ UserObserver executed successfully for {$user->username}");
            
            // Show current roles after sync
            $user->refresh();
            $roles = $user->roles->pluck('name')->join(', ') ?: 'None';
            $this->info("Current Orchid roles: {$roles}");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to execute UserObserver: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Handle all users with UserObserver logic
     */
    private function handleAllUsersObserver(bool $force): int
    {
        $users = User::whereNotIn('username', ['HAWKI'])->get();
        
        if ($users->isEmpty()) {
            $this->info('No users found.');
            return 0;
        }

        $this->info("Found {$users->count()} users to process with UserObserver");
        
        if (!$force && !$this->confirm('Do you want to trigger UserObserver for all users?')) {
            $this->info('Operation cancelled.');
            return 1;
        }

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $observer = new UserObserver();
        
        // Use reflection to call the private syncOrchidRole method
        $reflection = new \ReflectionClass($observer);
        $method = $reflection->getMethod('syncOrchidRole');
        $method->setAccessible(true);

        foreach ($users as $user) {
            try {
                $method->invoke($observer, $user);
                $successCount++;
            } catch (\Exception $e) {
                $this->line("\n❌ Failed for user {$user->username}: " . $e->getMessage());
                $errorCount++;
            }
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        $this->info("📊 UserObserver Summary: {$successCount} successful, {$errorCount} errors");
        
        return 0;
    }

    /**
     * Find user by username or ID
     */
    private function findUser(string $userParam): ?User
    {
        // Try to find by ID first (if numeric)
        if (is_numeric($userParam)) {
            $user = User::find($userParam);
            if ($user) {
                return $user;
            }
        }

        // Try to find by username
        return User::where('username', $userParam)->first();
    }
}
