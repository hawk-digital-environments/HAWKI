<?php

namespace App\Services;

use App\Models\Employeetype;
use App\Models\EmployeetypeRole;
use Illuminate\Support\Facades\Log;

class EmployeetypeMappingService
{
    /**
     * Map raw employeetype value from auth system to role slug
     * 
     * @param string $rawValue The raw value from authentication system
     * @param string $authMethod The authentication method (LDAP, OIDC, Shibboleth)
     * @return string The mapped role slug
     */
    public function mapEmployeetypeToRole(string $rawValue, string $authMethod): string
    {
        try {
            // Find or create employeetype entry
            $employeetype = Employeetype::findOrCreateForAuth($rawValue, $authMethod);
            
            // Log the mapping for debugging
            Log::info('Employeetype mapping processed', [
                'raw_value' => $rawValue,
                'auth_method' => $authMethod,
                'employeetype_id' => $employeetype->id,
                'display_name' => $employeetype->display_name,
                'was_created' => $employeetype->wasRecentlyCreated,
            ]);
            
            // Get the mapped role or fallback to guest
            $mappedRole = $employeetype->getMappedRoleSlug();
            
            // Additional logging for role assignment
            if ($mappedRole === 'guest' && !$employeetype->primaryRoleAssignment()) {
                Log::info('No role mapping found for employeetype, using guest fallback', [
                    'employeetype_id' => $employeetype->id,
                    'raw_value' => $rawValue,
                    'auth_method' => $authMethod,
                ]);
            }
            
            return $mappedRole;
            
        } catch (\Exception $e) {
            Log::error('Error in employeetype mapping', [
                'raw_value' => $rawValue,
                'auth_method' => $authMethod,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Fallback to guest on any error
            return 'guest';
        }
    }
    
    /**
     * Get all available employeetypes for a specific auth method
     * 
     * @param string $authMethod
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEmployeetypesForAuthMethod(string $authMethod)
    {
        return Employeetype::byAuthMethod($authMethod)
            ->active()
            ->with('roleAssignments')
            ->orderBy('display_name')
            ->get();
    }
    
    /**
     * Get all employeetypes with their role mappings for admin interface
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllEmployeetypesWithMappings()
    {
        return Employeetype::with(['roleAssignments' => function ($query) {
            $query->where('is_primary', true);
        }])
        ->active()
        ->orderBy('auth_method')
        ->orderBy('display_name')
        ->get();
    }
    
    /**
     * Assign a role to an employeetype
     * 
     * @param int $employeetypeId
     * @param string $roleSlug
     * @return bool
     */
    public function assignRoleToEmployeetype(int $employeetypeId, string $roleSlug): bool
    {
        try {
            $employeetype = Employeetype::find($employeetypeId);
            
            if (!$employeetype) {
                Log::warning('Attempted to assign role to non-existent employeetype', [
                    'employeetype_id' => $employeetypeId,
                    'role_slug' => $roleSlug,
                ]);
                return false;
            }
            
            EmployeetypeRole::assignRole($employeetypeId, $roleSlug, true);
            
            Log::info('Role assigned to employeetype', [
                'employeetype_id' => $employeetypeId,
                'employeetype_display_name' => $employeetype->display_name,
                'role_slug' => $roleSlug,
                'raw_value' => $employeetype->raw_value,
                'auth_method' => $employeetype->auth_method,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Error assigning role to employeetype', [
                'employeetype_id' => $employeetypeId,
                'role_slug' => $roleSlug,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Update employeetype display name and description
     * 
     * @param int $employeetypeId
     * @param string $displayName
     * @param string|null $description
     * @return bool
     */
    public function updateEmployeetypeDetails(int $employeetypeId, string $displayName, ?string $description = null): bool
    {
        try {
            $employeetype = Employeetype::find($employeetypeId);
            
            if (!$employeetype) {
                return false;
            }
            
            $employeetype->update([
                'display_name' => $displayName,
                'description' => $description,
            ]);
            
            Log::info('Employeetype details updated', [
                'employeetype_id' => $employeetypeId,
                'old_display_name' => $employeetype->getOriginal('display_name'),
                'new_display_name' => $displayName,
                'description' => $description,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Error updating employeetype details', [
                'employeetype_id' => $employeetypeId,
                'display_name' => $displayName,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
}
