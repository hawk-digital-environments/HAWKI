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
    /**
     * Migration files return an anonymous class, so each file is required exactly once
     * per process and the resulting (stateless) object is reused.
     *
     * @var array<string, object>
     */
    private static array $migrations = [];

    public function testMigrationPreservesGrantsSourcesAndReferencesAndCanRollBackEdits(): void
    {
        $this->bootLegacyDatabase();
        $sources = $this->assignmentSources();
        $migration = $this->migration();
        $migration->up();

        self::assertSame($sources, $this->assignmentSources());
        self::assertSame(2, DB::table('model_has_roles')->count());
        self::assertSame('researcher', Role::findOrFail(42)->name);
        self::assertSame('Research staff', Role::findOrFail(42)->display_name);
        self::assertSame(42, DB::table('employee_type_role_mappings')->value('role_id'));
        self::assertSame('[42]', DB::table('announcements')->value('target_roles'));
        self::assertSame(0, DB::table('permissions')->where('name', 'retired.permission')->count());
        $permissions = $this->permissions();
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
        // The edited grant is written back, and the unregistered legacy row survives rollback.
        self::assertSame(
            ['health.view', 'retired.permission'],
            DB::table('role_permissions')->where('role_id', 42)->orderBy('permission')->pluck('permission')->all(),
        );
        self::assertSame(
            ['admin.access', 'roles.manage'],
            DB::table('role_permissions')->where('role_id', 7)->orderBy('permission')->pluck('permission')->all(),
        );
        self::assertSame($sources, $this->assignmentSources());
        $migration->up();
        self::assertSame(['health.view'], $this->permissions()->permissionsOf($active));
        // The retired name is still not imported into the registry.
        self::assertSame(0, DB::table('permissions')->where('name', 'retired.permission')->count());
    }

    /**
     * MySQL auto-commits DDL, so a backfill (or a cache flush) that fails after the schema
     * statements leaves the migration unrecorded. Re-running it must repair the data.
     */
    public function testUpRestoresGrantsAndMembershipsAfterACommittedSchemaChangeWithNoBackfill(): void
    {
        $this->bootLegacyDatabase();
        $migration = $this->migration();
        $migration->up();

        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('permissions')->delete();

        $migration->up();

        self::assertSame(15, DB::table('permissions')->count());
        self::assertSame(3, DB::table('role_has_permissions')->count());
        self::assertSame(2, DB::table('model_has_roles')->count());
        self::assertSame(
            ['admin.access', 'roles.manage'],
            $this->permissions()->assignedPermissionsOf(User::withoutGlobalScopes()->findOrFail(32)),
        );
        self::assertSame(
            ['usage.view'],
            $this->permissions()->permissionsOf(User::withoutGlobalScopes()->findOrFail(31)),
        );
    }

    public function testRunningUpTwiceChangesNothingAndCreatesNoDuplicates(): void
    {
        $this->bootLegacyDatabase();
        $migration = $this->migration();
        $migration->up();
        $before = $this->migratedState();

        $migration->up();

        self::assertSame($before, $this->migratedState());
        self::assertSame(15, DB::table('permissions')->count());
        self::assertSame(3, DB::table('role_has_permissions')->count());
        self::assertSame(2, DB::table('model_has_roles')->count());
    }

    /**
     * Simulates a crash in between the two column renames, where `roles` is left holding
     * `slug` and `display_name` and no `name` at all.
     */
    public function testUpRecoversFromACrashBetweenTheTwoColumnRenames(): void
    {
        $this->bootLegacyDatabase();
        Schema::table('roles', static fn (Blueprint $table) => $table->renameColumn('name', 'display_name'));
        self::assertFalse(Schema::hasColumn('roles', 'name'));

        $this->migration()->up();

        self::assertSame('researcher', DB::table('roles')->where('id', 42)->value('name'));
        self::assertSame('Research staff', DB::table('roles')->where('id', 42)->value('display_name'));
        self::assertSame('web', DB::table('roles')->where('id', 42)->value('guard_name'));
        self::assertSame(3, DB::table('role_has_permissions')->count());
        self::assertSame(2, DB::table('model_has_roles')->count());
    }

    /**
     * `model_has_roles.model_id` is polymorphic and therefore has no foreign key. Spatie's
     * HasRoles::bootHasRoles() registers a `deleting` hook that detaches the roles of a model
     * that is not soft-deleting, which is what keeps the table from accumulating orphans.
     */
    public function testHardDeletingAUserRemovesItsRoleMemberships(): void
    {
        $this->bootLegacyDatabase();
        $this->migration()->up();
        self::assertSame(1, DB::table('model_has_roles')->where('model_id', 31)->count());

        User::withoutGlobalScopes()->findOrFail(31)->delete();

        self::assertSame(0, DB::table('model_has_roles')->where('model_id', 31)->count());
        self::assertSame(1, DB::table('model_has_roles')->count());
        self::assertSame(32, DB::table('model_has_roles')->value('model_id'));
    }

    /**
     * The tool and web-fetch migrations own the names the RBAC migration deliberately
     * leaves out. They register them without handing them to any role.
     */
    public function testToolAndWebFetchMigrationsRegisterNamesWithoutGrantingThem(): void
    {
        $this->bootLegacyDatabase();
        $this->migration()->up();
        Schema::create('ai_tools', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $tools = $this->migration('2026_09_15_130000_add_tool_access_rules');
        $webFetch = $this->migration('2026_09_15_140000_add_web_fetch_permission');

        $tools->up();
        $webFetch->up();
        // Both must be safe to retry after a committed schema change.
        $tools->up();
        $webFetch->up();

        self::assertSame(20, DB::table('permissions')->count());
        self::assertSame(
            [
                'ai.capabilities.image_generation.use',
                'ai.capabilities.web_fetch.use',
                'ai.capabilities.web_search.use',
                'tools.internal_search.use',
                'tools.use',
            ],
            DB::table('permissions')->where('name', 'like', '%use')->orderBy('name')->pluck('name')->all(),
        );
        self::assertSame(3, DB::table('role_has_permissions')->count());
        self::assertTrue(Schema::hasColumn('ai_tools', 'access_rule'));

        $webFetch->down();
        self::assertSame(0, DB::table('permissions')->where('name', 'ai.capabilities.web_fetch.use')->count());
        $tools->down();
        self::assertFalse(Schema::hasColumn('ai_tools', 'access_rule'));
    }

    private function migration(string $file = '2026_09_15_120000_migrate_rbac_to_spatie'): object
    {
        return self::$migrations[$file] ??= require database_path("migrations/{$file}.php");
    }

    /**
     * PermissionService is a scoped binding that memoizes per user; force a fresh read.
     */
    private function permissions(): PermissionService
    {
        $this->app->forgetScopedInstances();

        return app(PermissionService::class);
    }

    private function assignmentSources(): string
    {
        return DB::table('role_user')->orderBy('user_id')->orderBy('source')->get()->toJson();
    }

    /**
     * @return array<string, string>
     */
    private function migratedState(): array
    {
        return [
            'permissions' => DB::table('permissions')->orderBy('name')->get(['name', 'guard_name'])->toJson(),
            'role_has_permissions' => DB::table('role_has_permissions')
                ->orderBy('role_id')->orderBy('permission_id')->get()->toJson(),
            'model_has_roles' => DB::table('model_has_roles')
                ->orderBy('role_id')->orderBy('model_id')->get()->toJson(),
            'roles' => DB::table('roles')->orderBy('id')->get(['id', 'name', 'display_name', 'guard_name'])->toJson(),
        ];
    }

    private function bootLegacyDatabase(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->app->forgetScopedInstances();
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
