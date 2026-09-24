<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleGuard
{
    private int $mutationDepth = 0;

    public function __construct(private PermissionService $permissions)
    {
    }

    public function mutate(callable $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            DB::table('roles')->where('name', 'admin')->lockForUpdate()->first();
            $outermost = 0 === $this->mutationDepth;
            $hadAdmin = $outermost && $this->hasAdministrator();
            ++$this->mutationDepth;

            try {
                $result = $operation();
                // Grants may have changed; later checks in this request must not read the memo.
                $this->permissions->forget();

                if ($hadAdmin && !$this->hasAdministrator()) {
                    throw ValidationException::withMessages(['roles' => __('admin.errors.last_admin')]);
                }

                return $result;
            } finally {
                --$this->mutationDepth;
                // Also covers a failed operation and anything memoized from a rolled back state.
                $this->permissions->forget();
            }
        });
    }

    public function assertGrantable(array $permissions, User $actor): void
    {
        if (array_diff($permissions, $this->permissions->permissionsOf($actor))) {
            throw ValidationException::withMessages(['permissions' => __('admin.errors.permission_escalation')]);
        }
    }

    public function assertRolesGrantable(array $roles, User $actor): void
    {
        $this->assertGrantable($this->permissions->permissionsForRoles($roles), $actor);
    }

    public function assertEmployeeTypeGrantable(string $employeeType, User $actor): void
    {
        $role = DB::table('employee_type_role_mappings')->where('employee_type', $employeeType)->value('role_id');

        if (null !== $role) {
            $this->assertRolesGrantable([(int) $role], $actor);
        }
    }

    public function resyncTypes(array $types): void
    {
        User::withoutGlobalScopes()->whereIn('employeetype', $types)->orderBy('id')->chunkById(200, static function ($users): void {
            foreach ($users as $user) {
                app(EmployeeTypeRoleSyncer::class)->sync($user);
            }
        });
    }

    public function assertActorRetainsAccess(User $actor): void
    {
        $this->permissions->forget((int) $actor->getKey());

        if (!$this->permissions->has($actor, Permission::ACCESS)) {
            throw ValidationException::withMessages(['roles' => __('admin.errors.self_disable')]);
        }
    }

    /**
     * @phpstan-impure Reads grants that can change during a mutation.
     */
    private function hasAdministrator(): bool
    {
        $query = User::withoutGlobalScopes()->where('isRemoved', false)->where('admin_disabled', false);

        foreach ([Permission::ACCESS, Permission::ROLES_MANAGE] as $permission) {
            $query->whereHas('roles', static fn ($roles) => $roles->where('guard_name', 'web')
                ->whereHas('permissions', static fn ($permissions) => $permissions
                    ->where('guard_name', 'web')->where('name', $permission->value)));
        }

        return $query->exists();
    }
}
