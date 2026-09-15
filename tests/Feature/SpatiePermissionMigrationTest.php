<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Admin\PermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class SpatiePermissionMigrationTest extends TestCase
{
    public function testMigrationPreservesGrantsSourcesAndReferencesAndCanRollBackEdits(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->createLegacySchema();
        DB::table('users')->insert([
            ['id' => 31, 'admin_disabled' => false, 'isRemoved' => false],
            ['id' => 32, 'admin_disabled' => true, 'isRemoved' => false],
        ]);
        DB::table('roles')->insert([
            ['id' => 7, 'slug' => 'admin', 'name' => 'Administrator', 'is_system' => true],
            ['id' => 42, 'slug' => 'researcher', 'name' => 'Research staff', 'is_system' => false],
        ]);
        DB::table('role_permissions')->insert([
            ['role_id' => 7, 'permission' => 'admin.access'],
            ['role_id' => 7, 'permission' => 'roles.manage'],
            ['role_id' => 42, 'permission' => 'usage.view'],
            ['role_id' => 42, 'permission' => 'retired.permission'],
        ]);
        DB::table('role_user')->insert([
            ['role_id' => 42, 'user_id' => 31, 'source' => 'manual'],
            ['role_id' => 42, 'user_id' => 31, 'source' => 'employeetype'],
            ['role_id' => 7, 'user_id' => 32, 'source' => 'manual'],
        ]);
        DB::table('employee_type_role_mappings')->insert(['employee_type' => 'research', 'role_id' => 42]);
        DB::table('announcements')->insert(['target_roles' => '[42]']);
        $sources = DB::table('role_user')->orderBy('user_id')->orderBy('source')->get()->toJson();
        $migration = require database_path('migrations/2026_09_15_120000_migrate_rbac_to_spatie.php');
        $migration->up();

        self::assertSame($sources, DB::table('role_user')->orderBy('user_id')->orderBy('source')->get()->toJson());
        self::assertSame(2, DB::table('model_has_roles')->count());
        self::assertSame('researcher', Role::findOrFail(42)->name);
        self::assertSame('Research staff', Role::findOrFail(42)->display_name);
        self::assertSame(42, DB::table('employee_type_role_mappings')->value('role_id'));
        self::assertSame('[42]', DB::table('announcements')->value('target_roles'));
        self::assertSame(0, DB::table('permissions')->where('name', 'retired.permission')->count());
        $permissions = app(PermissionService::class);
        $active = User::withoutGlobalScopes()->findOrFail(31);
        $disabled = User::withoutGlobalScopes()->findOrFail(32);
        self::assertSame(['usage.view'], $permissions->permissionsOf($active));
        self::assertSame([], $permissions->permissionsOf($disabled));
        self::assertSame(['admin.access', 'roles.manage'], $permissions->assignedPermissionsOf($disabled));

        Role::findOrFail(42)->syncPermissions(['health.view']);
        $migration->down();
        self::assertFalse(Schema::hasTable('model_has_roles'));
        self::assertSame('researcher', DB::table('roles')->where('id', 42)->value('slug'));
        self::assertSame('Research staff', DB::table('roles')->where('id', 42)->value('name'));
        self::assertSame(['health.view'], DB::table('role_permissions')->where('role_id', 42)->pluck('permission')->all());
        self::assertSame($sources, DB::table('role_user')->orderBy('user_id')->orderBy('source')->get()->toJson());
        $migration->up();
        self::assertSame(['health.view'], $permissions->permissionsOf($active));
    }

    private function createLegacySchema(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->boolean('admin_disabled');
            $table->boolean('isRemoved');
        });
        Schema::create('roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        Schema::create('role_permissions', static function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->primary(['role_id', 'permission']);
        });
        Schema::create('role_user', static function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['role_id', 'user_id', 'source']);
        });
        Schema::create('employee_type_role_mappings', static function (Blueprint $table): void {
            $table->string('employee_type')->unique();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
        });
        Schema::create('announcements', static function (Blueprint $table): void {
            $table->json('target_roles');
        });
    }
}
