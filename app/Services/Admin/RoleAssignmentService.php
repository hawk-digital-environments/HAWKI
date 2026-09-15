<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoleAssignmentService
{
    public function __construct(private RoleGuard $guard)
    {
    }

    /**
     * Replace one assignment source, retaining every other source.
     */
    public function replace(User $user, array $roles, string $source = 'manual'): void
    {
        if (!\in_array($source, ['manual', 'employeetype'], true)) {
            throw new \InvalidArgumentException('Unknown role assignment source.');
        }

        $this->guard->mutate(static function () use ($user, $roles, $source): void {
            DB::table('role_user')->where('user_id', $user->id)->where('source', $source)->delete();

            foreach (array_unique($roles) as $role) {
                Role::query()->where('guard_name', 'web')->findOrFail($role);
                DB::table('role_user')->insert([
                    'user_id' => $user->id, 'role_id' => $role, 'source' => $source, 'created_at' => now(),
                ]);
            }

            $effective = DB::table('role_user')->where('user_id', $user->id)->distinct()->pluck('role_id')->all();
            // syncRoles accepts role models and refreshes its membership relation.
            $user->syncRoles(Role::query()->whereKey($effective)->where('guard_name', 'web')->get());
            $user->unsetRelation('roles')->unsetRelation('permissions');
        });
    }

    public function setManual(User $user, int $roleId, bool $granted): void
    {
        $this->guard->mutate(function () use ($user, $roleId, $granted): void {
            $roles = DB::table('role_user')->where('user_id', $user->id)->where('source', 'manual')
                ->pluck('role_id')->map(static fn ($id) => (int) $id)->all();
            $this->replace($user, $granted ? [...$roles, $roleId] : array_diff($roles, [$roleId]));
        });
    }
}
