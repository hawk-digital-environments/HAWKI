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
    private const PERMISSION = 'ai.capabilities.web_fetch.use';

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        // No role receives this grant. Administrators publish it explicitly.
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            ['name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ]);
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
