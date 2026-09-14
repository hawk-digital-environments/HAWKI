<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleGuard
{
    public function __construct(
        private PermissionService $permissions,
        private EmployeeTypeRoleSyncer $syncer,
    ) {
    }

    public function mutate(callable $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            DB::table('roles')->where('slug', 'admin')->lockForUpdate()->first();
            $hadAdmin = $this->hasAdministrator();
            $result = $operation();

            if ($hadAdmin && !$this->hasAdministrator()) {
                throw ValidationException::withMessages(['roles' => __('admin.errors.last_admin')]);
            }

            return $result;
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
        $this->assertGrantable(DB::table('role_permissions')->whereIn('role_id', $roles)->pluck('permission')->all(), $actor);
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
        User::withoutGlobalScopes()->whereIn('employeetype', $types)->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                $this->syncer->sync($user);
            }
        });
    }

    public function assertActorRetainsAccess(User $actor): void
    {
        if (!$this->permissions->has($actor, Permission::ACCESS)) {
            throw ValidationException::withMessages(['roles' => __('admin.errors.self_disable')]);
        }
    }

    private function hasAdministrator(): bool
    {
        return DB::table('users')->where('isRemoved', false)->where('admin_disabled', false)
            ->whereExists(static function ($q): void {
                $q->selectRaw('1')->from('role_user')->join('role_permissions', 'role_permissions.role_id', '=', 'role_user.role_id')
                    ->whereColumn('role_user.user_id', 'users.id')->where('permission', 'admin.access');
            })->whereExists(static function ($q): void {
                $q->selectRaw('1')->from('role_user')->join('role_permissions', 'role_permissions.role_id', '=', 'role_user.role_id')
                    ->whereColumn('role_user.user_id', 'users.id')->where('permission', 'roles.manage');
            })->exists();
    }
}
