<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;

/**
 * Resolves effective administration grants straight from the database.
 *
 * Grants are never read from Spatie's worker-local caches, so a revocation takes effect
 * without waiting for a worker to recycle. Within one request (or one queued job) the
 * resolution is memoized: the Gate callback runs on every single ability check, and repeating
 * four queries per check is not what "read fresh" means. The service is a scoped binding, so
 * every request/job starts with an empty memo.
 *
 * The memo is keyed by the *user instance*, not by the user id. Callers that must observe a
 * change made by another request mid-flight — tool authorization re-loads the actor before
 * every dispatch — get a fresh resolution simply by handing over a freshly loaded model,
 * while callers that keep passing the authenticated user resolve once. Writes made in this
 * process go through RoleGuard::mutate(), which calls {@see forget()} after the operation and
 * again when it unwinds.
 */
class PermissionService
{
    /**
     * @var \WeakMap<User, bool>
     */
    private \WeakMap $eligible;

    /**
     * @var \WeakMap<User, array>
     */
    private \WeakMap $assigned;

    public function __construct()
    {
        $this->eligible = new \WeakMap();
        $this->assigned = new \WeakMap();
    }

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
        return $this->assigned[$user] ??= $this->readAssignedPermissions($user);
    }

    public function isEligible(User $user): bool
    {
        return $this->eligible[$user] ??= $this->readEligibility($user);
    }

    /**
     * Drops the memoized resolution for one user id, or for every user.
     */
    public function forget(?int $userId = null): void
    {
        if (null === $userId) {
            $this->eligible = new \WeakMap();
            $this->assigned = new \WeakMap();

            return;
        }

        foreach ([$this->eligible, $this->assigned] as $memo) {
            $stale = [];

            foreach ($memo as $user => $_) {
                if ((int) $user->getKey() === $userId) {
                    $stale[] = $user;
                }
            }

            foreach ($stale as $user) {
                unset($memo[$user]);
            }
        }
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
        return \in_array(self::nameOf($permission), $this->permissionsOf($user), true);
    }

    public function authorize(?User $user, Permission|string $permission): void
    {
        // One resolution answers both checks.
        $granted = $this->permissionsOf($user);

        abort_unless(\in_array(Permission::ACCESS->value, $granted, true) && \in_array(self::nameOf($permission), $granted, true), 403);
    }

    public function roleIds(User $user): array
    {
        return $user->roles()->where('guard_name', 'web')->pluck('roles.id')->map(static fn ($id) => (int) $id)->all();
    }

    private function readAssignedPermissions(User $user): array
    {
        // Query the relationship rather than a previously loaded user's grants.
        return $this->permissionsForRoles($this->roleIds($user));
    }

    private function readEligibility(User $user): bool
    {
        return !$user->admin_disabled && !$user->isRemoved
            && User::withoutGlobalScopes()->whereKey($user->getKey())
                ->where('admin_disabled', false)->where('isRemoved', false)->exists();
    }

    private static function nameOf(Permission|string $permission): string
    {
        return $permission instanceof Permission ? $permission->value : $permission;
    }
}
