<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;

class PermissionService
{
    public function permissionsOf(?User $user): array
    {
        if (!$user || !$this->isEligible($user)) {
            return [];
        }

        return $this->assignedPermissionsOf($user);
    }

    /**
     * Permissions the user's roles grant regardless of whether the account is disabled or removed.
     * Use this when checking what a *target* account would hold once re-enabled, so a disabled
     * administrator cannot be taken over by an actor who could not grant those permissions.
     */
    public function assignedPermissionsOf(User $user): array
    {
        // Query the relationship rather than a previously loaded user's grants.
        return $this->permissionsForRoles($this->roleIds($user));
    }

    public function isEligible(User $user): bool
    {
        return !$user->admin_disabled && !$user->isRemoved
            && User::withoutGlobalScopes()->whereKey($user->getKey())
                ->where('admin_disabled', false)->where('isRemoved', false)->exists();
    }

    public function permissionsForRoles(array $roles): array
    {
        return Role::query()->whereKey($roles)->where('guard_name', 'web')
            ->with(['permissions' => static fn ($query) => $query->where('guard_name', 'web')->whereIn('name', Permission::values())])
            ->get()->flatMap(static fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()->sort()->values()->all();
    }

    public function has(?User $user, Permission|string $permission): bool
    {
        return \in_array($permission instanceof Permission ? $permission->value : $permission, $this->permissionsOf($user), true);
    }

    public function authorize(?User $user, Permission|string $permission): void
    {
        abort_unless($this->has($user, Permission::ACCESS) && $this->has($user, $permission), 403);
    }

    public function roleIds(User $user): array
    {
        return $user->roles()->where('guard_name', 'web')->pluck('roles.id')->map(static fn ($id) => (int) $id)->all();
    }
}
