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

            // Map employeetype to role slug
            $requiredRoleSlug = $this->mapEmployeeTypeToRoleSlug($user->employeetype);

            if (! $requiredRoleSlug) {
                Log::warning("Unknown employeetype for role mapping: {$user->employeetype} for user {$user->id}");

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
                // Note: Role assignment is logged in AuthenticationController during registration
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
     */
    private function mapEmployeeTypeToRoleSlug(string $employeetype): ?string
    {
        try {
            // Determine auth method from config
            $authMethod = config('auth.authentication_method', 'LDAP');
            
            // Resolve EmployeetypeMappingService from container
            $mappingService = app(EmployeetypeMappingService::class);
            
            // Use EmployeetypeMappingService to map employeetype to role
            $roleSlug = $mappingService->mapEmployeetypeToRole($employeetype, $authMethod);
            
            // The service returns 'guest' as fallback, but we want null for unknown employeetypes
            // to trigger appropriate logging in the caller
            if ($roleSlug === 'guest') {
                Log::info("Employeetype '{$employeetype}' mapped to fallback 'guest' role. Check Role Assignment Screen to configure proper mapping.");
                return $roleSlug;
            }
            
            return $roleSlug;
            
        } catch (\Exception $e) {
            Log::error("Failed to map employeetype '{$employeetype}' to role: " . $e->getMessage());
            return null;
        }
    }
}
