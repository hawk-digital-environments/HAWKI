<?php

namespace App\Observers;

use App\Models\User;
use App\Services\EmployeetypeMappingService;
use Illuminate\Support\Facades\Log;
use Orchid\Platform\Models\Role;

class UserObserver
{
    /**
     * Handle the User "created" event.
     * This will sync the Orchid role for new users
     */
    public function created(User $user): void
    {
        $this->syncOrchidRole($user);
    }

    /**
     * Handle the User "updated" event.
     * This will sync the Orchid role based on employeetype and approval status
     */
    public function updated(User $user): void
    {
        // Only sync if employeetype was changed OR approval was changed from false to true
        if ($user->wasChanged('employeetype') ||
            ($user->wasChanged('approval') && $user->approval)) {
            $this->syncOrchidRole($user);
        }

        // Handle approval deactivation separately (remove all roles)
        if ($user->wasChanged('approval') && ! $user->approval) {
            $removedRoles = $user->roles()->get();
            $user->roles()->detach();

            if ($removedRoles->count() > 0) {
                $roleNames = $removedRoles->pluck('name')->implode(', ');
                Log::info("Removed all Orchid roles for unapproved user {$user->id} ({$user->username}): {$roleNames}");
            }
        }
    }

    /**
     * Sync Orchid role based on user's employeetype and approval status
     */
    private function syncOrchidRole(User $user): void
    {
        try {
            // Skip system users (AI/HAWKI user)
            if ($this->isSystemUser($user)) {
                Log::info("Skipping role sync for system user: {$user->username} (ID: {$user->id})");

                return;
            }

            // Skip if no employeetype is set
            if (empty($user->employeetype)) {
                return;
            }

            // Skip if user is not approved
            if (! $user->approval) {
                Log::info("Skipping role sync for unapproved user: {$user->username} (ID: {$user->id})");

                return;
            }

            // Map employeetype to role slug (this also creates the employeetype entry in DB)
            $requiredRoleSlug = $this->mapEmployeeTypeToRoleSlug($user->employeetype, $user->auth_type ?? 'LDAP');

            // If no mapping exists, don't assign any role - admin must configure mapping first
            if (! $requiredRoleSlug) {
                Log::info("No role mapping configured for employeetype '{$user->employeetype}' - skipping role assignment. Admin can configure mapping in Role Assignment Screen.", [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'employeetype' => $user->employeetype,
                    'auth_type' => $user->auth_type ?? 'LDAP',
                ]);
                return;
            }

            // Find the corresponding Orchid role
            $requiredRole = Role::where('slug', $requiredRoleSlug)->first();

            if (! $requiredRole) {
                Log::error("Orchid role not found for slug: {$requiredRoleSlug} (employeetype: {$user->employeetype}) for user {$user->id}");

                return;
            }

            // Add the required role if not already present (never remove any roles)
            if (! $user->roles()->where('roles.id', $requiredRole->id)->exists()) {
                $user->roles()->attach($requiredRole->id);
                
                Log::info("Role assigned to user via employeetype mapping", [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'employeetype' => $user->employeetype,
                    'assigned_role' => $requiredRoleSlug,
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Failed to sync Orchid role for user {$user->id}: ".$e->getMessage());
        }
    }

    /**
     * Check if user is a system user that should be excluded from role sync
     */
    private function isSystemUser(User $user): bool
    {
        // System users that should not have Orchid roles assigned
        $systemUsernames = ['HAWKI'];

        return in_array($user->username, $systemUsernames, true);
    }

    /**
     * Map employeetype values to Orchid role slugs using the EmployeetypeMappingService
     * This allows for automatic discovery and mapping of new employeetypes
     * 
     * @param string $employeetype Raw employeetype value from auth system
     * @param string $authMethod Authentication method (LDAP, OIDC, Shibboleth)
     * @return string|null Role slug or null if no mapping exists
     */
    private function mapEmployeeTypeToRoleSlug(string $employeetype, string $authMethod = 'LDAP'): ?string
    {
        try {
            // Resolve EmployeetypeMappingService from container
            $mappingService = app(EmployeetypeMappingService::class);
            
            // Use EmployeetypeMappingService to map employeetype to role
            // This will automatically create an employeetype entry in the database if it doesn't exist
            $roleSlug = $mappingService->mapEmployeetypeToRole($employeetype, $authMethod);
            
            // Check if this is the fallback 'guest' role (meaning no real mapping exists)
            if ($roleSlug === 'guest') {
                // Check if there's an actual mapping to guest role or if it's just the fallback
                $employeetypeEntry = \App\Models\Employeetype::where('raw_value', $employeetype)
                    ->where('auth_method', $authMethod)
                    ->first();
                
                if ($employeetypeEntry) {
                    $primaryRole = $employeetypeEntry->primaryRoleAssignment();
                    
                    // If there's no actual role assignment, return null (no mapping configured)
                    if (!$primaryRole || !$primaryRole->role) {
                        Log::info("Employeetype '{$employeetype}' (auth: {$authMethod}) has no role mapping configured - no role will be assigned.", [
                            'employeetype' => $employeetype,
                            'auth_method' => $authMethod,
                            'employeetype_entry_id' => $employeetypeEntry->id,
                        ]);
                        return null;
                    }
                    
                    // There is an actual mapping to guest role
                    return $primaryRole->role->slug;
                }
            }
            
            return $roleSlug;
            
        } catch (\Exception $e) {
            Log::error("Failed to map employeetype '{$employeetype}' to role: " . $e->getMessage(), [
                'employeetype' => $employeetype,
                'auth_method' => $authMethod,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return null instead of fallback - no role should be assigned on error
            return null;
        }
    }
}
