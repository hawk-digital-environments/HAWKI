<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class() extends Migration {
    /**
     * Frozen name, deliberately not read from App\Services\Admin\Permission so a later edit
     * of that enum cannot change what this migration writes. Registered with the query builder
     * so the migration depends on neither Spatie's model class nor the registrar cache.
     */
    private const PERMISSION = 'assistants.manage';

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            ['name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // An administration-management permission, like `roles.manage` or `models.manage`:
        // the built-in administrator role receives it automatically, same as those.
        $permissionId = DB::table('permissions')->where('guard_name', 'web')->where('name', self::PERMISSION)->value('id');
        $adminRoleId = DB::table('roles')->where('guard_name', 'web')->where('name', 'admin')->value('id');
        if (null !== $permissionId && null !== $adminRoleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                ['permission_id' => $permissionId, 'role_id' => $adminRoleId],
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        // role_has_permissions cascades on the permission foreign key.
        DB::table('permissions')->where('guard_name', 'web')->where('name', self::PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
