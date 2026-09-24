<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/** Adds roles, granular permissions and access rules to an existing Administration installation. */
return new class extends Migration {
    /**
     * Frozen copy of the administration permission names registered when this migration was
     * written. Deliberately not read from App\Services\Admin\Permission: a later edit of that
     * enum must not retroactively change what a fresh install seeds. The built-in administrator
     * role receives every registered permission.
     */
    private const ADMIN_PERMISSIONS = [
        'admin.access',
        'users.view',
        'users.manage',
        'roles.manage',
        'models.manage',
        'providers.manage',
        'mcp.manage',
        'announcements.manage',
        'usage.view',
        'usage.view-per-user',
        'health.view',
        'health.manage',
        'settings.view',
        'settings.manage',
        'external-apps.manage',
    ];

    /**
     * Tool and AI capability permission names. Registered for the built-in administrator; no
     * other role receives them until access rules and grants are published explicitly.
     */
    private const CAPABILITY_PERMISSIONS = [
        'tools.use',
        'ai.capabilities.web_search.use',
        'ai.capabilities.web_fetch.use',
        'ai.capabilities.image_generation.use',
        'tools.internal_search.use',
    ];

    private const CHUNK = 500;

    public function up(): void
    {
        $this->createRbacTables();

        if (!Schema::hasColumn('ai_tools', 'access_rule')) {
            Schema::table('ai_tools', static function (Blueprint $table): void {
                $table->string('access_rule', 80)->default('unavailable')->index();
            });
        }

        if (!Schema::hasColumn('announcements', 'target_roles')) {
            Schema::table('announcements', static function (Blueprint $table): void {
                $table->json('target_roles')->nullable();
            });
        }

        $this->seed();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_tools', 'access_rule')) {
            Schema::table('ai_tools', static function (Blueprint $table): void {
                $table->dropIndex(['access_rule']);
                $table->dropColumn('access_rule');
            });
        }

        if (Schema::hasColumn('announcements', 'target_roles')) {
            Schema::table('announcements', static fn (Blueprint $table) => $table->dropColumn('target_roles'));
        }

        foreach ([
            'model_has_permissions', 'model_has_roles', 'role_has_permissions',
            'employee_type_role_mappings', 'role_user', 'permissions', 'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createRbacTables(): void
    {
        // Spatie's stock schema plus HAWKI's display_name, description and is_system.
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', static function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->string('display_name');
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', static function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', static function (Blueprint $table): void {
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', static function (Blueprint $table): void {
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        // Required by HasRoles; HAWKI only authorizes grants inherited from roles.
        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', static function (Blueprint $table): void {
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            });
        }

        // Records why a user holds a role (manual grant, employee type, ...). Spatie's
        // model_has_roles holds the effective, deduplicated membership derived from it.
        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', static function (Blueprint $table): void {
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('source')->default('manual');
                $table->timestamp('created_at')->useCurrent();
                $table->primary(['role_id', 'user_id', 'source']);
            });
        }

        if (!Schema::hasTable('employee_type_role_mappings')) {
            Schema::create('employee_type_role_mappings', static function (Blueprint $table): void {
                $table->id();
                $table->string('employee_type')->unique();
                $table->foreignId('role_id')->constrained()->restrictOnDelete();
                $table->timestamps();
            });
        }
    }

    private function seed(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('roles')->insertOrIgnore([
                ['name' => 'admin', 'guard_name' => 'web', 'display_name' => 'Administrator', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'user', 'guard_name' => 'web', 'display_name' => 'User', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
            $admin = (int) DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');

            DB::table('permissions')->insertOrIgnore(array_map(
                static fn (string $name): array => ['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
                [...self::ADMIN_PERMISSIONS, ...self::CAPABILITY_PERMISSIONS],
            ));

            DB::table('role_has_permissions')->insertOrIgnore(
                DB::table('permissions')->where('guard_name', 'web')->whereIn('name', [...self::ADMIN_PERMISSIONS, ...self::CAPABILITY_PERMISSIONS])
                    ->pluck('id')->map(static fn ($id): array => ['permission_id' => (int) $id, 'role_id' => $admin])->all(),
            );

            DB::table('employee_type_role_mappings')->insertOrIgnore([
                'employee_type' => 'admin', 'role_id' => $admin, 'created_at' => $now, 'updated_at' => $now,
            ]);

            // Existing administrators keep their access as a manual grant, so a later change of
            // their employee type does not lock them out.
            $morphClass = (new User())->getMorphClass();
            DB::table('users')->where('employeetype', 'admin')->orderBy('id')->chunkById(self::CHUNK, static function (Collection $users) use ($admin, $now, $morphClass): void {
                DB::table('role_user')->insertOrIgnore($users->map(static fn ($user): array => [
                    'role_id' => $admin, 'user_id' => $user->id, 'source' => 'manual', 'created_at' => $now,
                ])->all());
                DB::table('model_has_roles')->insertOrIgnore($users->map(static fn ($user): array => [
                    'role_id' => $admin, 'model_id' => $user->id, 'model_type' => $morphClass,
                ])->all());
            });
        });
    }

};
