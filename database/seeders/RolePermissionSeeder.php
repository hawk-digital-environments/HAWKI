<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the roles' permission grants and the users' role assignments for a
 * dev instance: `admin` (the first user) receives every permission, everyone
 * else receives the `user` role, which may use tools and all well-known AI
 * capabilities. Mirrors {@see \App\Services\Admin\RoleAssignmentService} by
 * writing `role_user` (source `manual`) and syncing the Spatie relation, so
 * the admin UI shows the seeded assignments as "Manually assigned".
 *
 * Idempotent: re-running adds missing grants without revoking anything an
 * administrator published in between.
 */
class RolePermissionSeeder extends Seeder
{
    private const USER_ROLE_PERMISSIONS = [
        Permission::TOOLS_USE,
        Permission::WEB_SEARCH_USE,
        Permission::WEB_FETCH_USE,
        Permission::IMAGE_GENERATION_USE,
        Permission::INTERNAL_SEARCH_USE,
    ];

    public function run(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('role_user')) {
            $this->command?->warn('RolePermissionSeeder: roles/permissions tables not found — skipping.');
            return;
        }

        $this->seedPermissions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = $this->grant('admin', 'Administrator', Permission::values());
        $userRole = $this->grant('user', 'User', array_map(static fn (Permission $permission): string => $permission->value, self::USER_ROLE_PERMISSIONS));

        $this->assign($adminRole, $userRole);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Makes sure every {@see Permission} case exists as a `web`-guard row,
     * like the tool-access-rule migrations do.
     */
    private function seedPermissions(): void
    {
        $now = now();
        foreach (Permission::values() as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param list<string> $permissions
     */
    private function grant(string $name, string $displayName, array $permissions): Role
    {
        $role = Role::firstOrCreate(
            ['name' => $name],
            ['display_name' => $displayName],
        );
        $role->givePermissionTo($permissions);

        return $role;
    }

    private function assign(Role $adminRole, Role $userRole): void
    {
        $users = User::withoutGlobalScopes()->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->command?->warn('RolePermissionSeeder: no users found — nothing to assign.');
            return;
        }

        foreach ($users as $index => $user) {
            $role = 0 === $index ? $adminRole : $userRole;

            DB::table('role_user')->insertOrIgnore([
                'role_id' => $role->getKey(),
                'user_id' => $user->getKey(),
                'source' => 'manual',
                'created_at' => now(),
            ]);

            $effectiveRoleIds = DB::table('role_user')
                ->where('user_id', $user->getKey())
                ->distinct()
                ->pluck('role_id')
                ->all();

            $user->syncRoles(Role::query()->whereKey($effectiveRoleIds)->where('guard_name', 'web')->get());
        }
    }
}
