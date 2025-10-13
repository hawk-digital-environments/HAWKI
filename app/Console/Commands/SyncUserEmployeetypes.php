<?php

namespace App\Console\Commands;

use App\Models\Employeetype;
use App\Models\User;
use App\Services\EmployeetypeMappingService;
use Illuminate\Console\Command;
use Orchid\Platform\Models\Role;

class SyncUserEmployeetypes extends Command
{
    protected $signature = 'user:sync-employeetypes {username? : Specific username to sync} {--all : Sync all users}';

    protected $description = 'Synchronize user employeetypes with role mappings and create missing employeetype entries';

    public function handle()
    {
        $username = $this->argument('username');
        $syncAll = $this->option('all');

        if (!$username && !$syncAll) {
            $this->error('Please provide a username or use --all flag');
            $this->info('Usage:');
            $this->info('  php artisan user:sync-employeetypes --all');
            return 1;
        }

        $mappingService = app(EmployeetypeMappingService::class);

        if ($syncAll) {
            $users = User::whereNotNull('employeetype')
                ->where('employeetype', '!=', '')
                ->get();
            
            $this->info("Syncing {$users->count()} users with employeetypes...");
            $this->info('');
        } else {
            $users = User::where('username', $username)->get();
            
            if ($users->isEmpty()) {
                $this->error("User '{$username}' not found!");
                return 1;
            }
        }

        $synced = 0;
        $created = 0;
        $rolesAssigned = 0;

        foreach ($users as $user) {
            $this->line("Processing user: {$user->username} (employeetype: {$user->employeetype})");

            // Determine auth method
            $authMethod = match($user->auth_type ?? 'ldap') {
                'ldap' => 'LDAP',
                'oidc' => 'OIDC',
                'shibboleth' => 'Shibboleth',
                'local' => 'system',
                default => 'LDAP',
            };

            // Check if employeetype entry exists
            $employeetypeEntry = Employeetype::where('raw_value', $user->employeetype)
                ->where('auth_method', $authMethod)
                ->first();

            if (!$employeetypeEntry) {
                $this->comment("  → Creating new employeetype entry for '{$user->employeetype}' ({$authMethod})");
                $created++;
            }

            // Map employeetype to role (this will create the entry if it doesn't exist)
            $roleSlug = $mappingService->mapEmployeetypeToRole($user->employeetype, $authMethod);

            // Re-fetch to show the created entry
            $employeetypeEntry = Employeetype::where('raw_value', $user->employeetype)
                ->where('auth_method', $authMethod)
                ->first();

            if ($employeetypeEntry) {
                $this->info("  ✓ Employeetype entry exists (ID: {$employeetypeEntry->id})");
                
                $primaryRole = $employeetypeEntry->primaryRoleAssignment();
                if ($primaryRole && $primaryRole->role) {
                    $this->info("  ✓ Mapped to role: {$primaryRole->role->name} ({$primaryRole->role->slug})");
                    $roleSlug = $primaryRole->role->slug;
                } else {
                    $this->comment("  ⚠ No role mapping configured - no role will be assigned");
                    $this->comment("  → Admin must configure mapping in Role Assignment Screen");
                    $roleSlug = null;
                }
            }

            // Assign role to user if approved and mapping exists
            if ($user->approval && $roleSlug) {
                $role = Role::where('slug', $roleSlug)->first();
                
                if ($role) {
                    if (!$user->roles()->where('roles.id', $role->id)->exists()) {
                        $user->roles()->attach($role->id);
                        $this->info("  ✓ Assigned '{$roleSlug}' role to user");
                        $rolesAssigned++;
                    } else {
                        $this->line("  → User already has '{$roleSlug}' role");
                    }
                } else {
                    $this->error("  ✗ Role '{$roleSlug}' not found in database!");
                }
            } elseif ($user->approval && !$roleSlug) {
                $this->comment("  → No role assigned (waiting for admin to configure mapping)");
            } elseif (!$user->approval) {
                $this->comment("  ⚠ User not approved - skipping role assignment");
            }

            $synced++;
            $this->info('');
        }

        $this->info('========================================');
        $this->info('SYNC COMPLETED');
        $this->info('========================================');
        $this->info("Users processed: {$synced}");
        $this->info("New employeetype entries created: {$created}");
        $this->info("Roles assigned: {$rolesAssigned}");
        $this->info('');
        $this->info('💡 To configure role mappings, visit:');
        $this->info('   → ' . url('/admin/role-assignments'));

        return 0;
    }
}
