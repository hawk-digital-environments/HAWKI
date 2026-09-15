<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function permissionsOf(?User $user): array
    {
        if (!$user || $user->admin_disabled || $user->isRemoved) return [];

        return $this->assignedPermissionsOf($user);
    }

    /**
     * Permissions the user's roles grant regardless of whether the account is disabled or removed.
     * Use this when checking what a *target* account would hold once re-enabled, so a disabled
     * administrator cannot be taken over by an actor who could not grant those permissions.
     */
    public function assignedPermissionsOf(User $user): array
    {
        return DB::table('role_permissions')
            ->join('role_user', 'role_user.role_id', '=', 'role_permissions.role_id')
            ->where('role_user.user_id', $user->id)
            ->whereIn('permission', Permission::values())
            ->distinct()->pluck('permission')->all();
    }

    public function has(?User $user, Permission|string $permission): bool
    {
        return in_array($permission instanceof Permission ? $permission->value : $permission, $this->permissionsOf($user), true);
    }

    public function authorize(?User $user, Permission|string $permission): void
    {
        abort_unless($this->has($user, Permission::ACCESS) && $this->has($user, $permission), 403);
    }

    public function roleIds(User $user): array
    {
        return DB::table('role_user')->where('user_id', $user->id)->distinct()->pluck('role_id')->map(fn($id) => (int)$id)->all();
    }
}
