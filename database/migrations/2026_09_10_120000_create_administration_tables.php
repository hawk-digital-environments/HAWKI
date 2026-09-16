<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Administration: Spatie roles and permissions, the admin tables, and the admin columns on
 * `users` and the AI configuration tables.
 *
 * MySQL auto-commits DDL, so a crash between any two statements would leave the migration
 * unrecorded and otherwise unrepeatable. Every schema step is therefore guarded and every seed
 * ignores rows that already exist, which makes up() restartable and down() safe on a half-applied
 * schema.
 */
return new class() extends Migration {
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

    private const ADMIN_MANAGED_TABLES = [
        'ai_providers', 'ai_models', 'mcp_servers', 'ai_tools', 'system_models', 'system_prompts', 'ai_model_descriptions',
    ];

    private const CHUNK = 500;

    public function up(): void
    {
        $this->createRbacTables();
        $this->createAdminTables();
        $this->addColumns();
        $this->seed();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $this->dropColumns();

        foreach ([
            'model_has_permissions', 'model_has_roles', 'role_has_permissions', 'permissions',
            'usage_daily_totals', 'admin_deleted_records', 'admin_audit_log', 'admin_settings',
            'employee_type_role_mappings', 'role_user', 'roles',
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

    private function createAdminTables(): void
    {
        if (!Schema::hasTable('admin_settings')) {
            Schema::create('admin_settings', static function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->json('value');
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin_audit_log')) {
            Schema::create('admin_audit_log', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action');
                $table->string('resource_type');
                $table->string('resource_id')->nullable();
                $table->json('changes');
                $table->ipAddress('ip')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Configuration records deleted in Administration, keyed like the deployment files name
        // them, so imports do not recreate them. See App\Services\Admin\DeletedRecords.
        if (!Schema::hasTable('admin_deleted_records')) {
            Schema::create('admin_deleted_records', static function (Blueprint $table): void {
                $table->id();
                $table->string('resource', 50);
                // MCP server urls can exceed the index key length, so the unique key hashes the identity.
                $table->text('identity');
                $table->char('identity_hash', 64);
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['resource', 'identity_hash']);
            });
        }

        if (!Schema::hasTable('usage_daily_totals')) {
            Schema::create('usage_daily_totals', static function (Blueprint $table): void {
                $table->id();
                $table->date('day');
                $table->unsignedBigInteger('user_id');
                $table->string('model');
                $table->string('type');
                $table->unsignedBigInteger('requests');
                $table->unsignedBigInteger('prompt_tokens');
                $table->unsignedBigInteger('completion_tokens');
                $table->unique(['day', 'user_id', 'model', 'type'], 'usage_daily_dimensions');
            });
        }
    }

    private function addColumns(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'admin_disabled')) {
                $table->boolean('admin_disabled')->default(false);
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'local_password')) {
                $table->string('local_password')->nullable()->after('username');
            }
        });

        foreach (self::ADMIN_MANAGED_TABLES as $name) {
            if (!Schema::hasColumn($name, 'admin_managed')) {
                Schema::table($name, static fn (Blueprint $table) => $table->boolean('admin_managed')->default(false));
            }
        }

        Schema::table('ai_providers', static function (Blueprint $table): void {
            if (!Schema::hasColumn('ai_providers', 'additional_config')) {
                $table->text('additional_config')->nullable();
            }
            if (!Schema::hasColumn('ai_providers', 'icon')) {
                $table->json('icon')->nullable();
            }
        });

        // Every tool starts unavailable; administrators publish access rules explicitly.
        if (!Schema::hasColumn('ai_tools', 'access_rule')) {
            Schema::table('ai_tools', static function (Blueprint $table): void {
                $table->string('access_rule', 80)->default('unavailable')->index();
            });
        }

        // Descriptions belong to their model: deleting a model in Administration must not leave them behind.
        if (!$this->hasModelDescriptionForeignKey()) {
            DB::table('ai_model_descriptions')->whereNotIn('ai_model_id', DB::table('ai_models')->select('id'))->delete();
            Schema::table('ai_model_descriptions', static function (Blueprint $table): void {
                $table->foreign('ai_model_id')->references('id')->on('ai_models')->cascadeOnDelete();
            });
        }
    }

    private function dropColumns(): void
    {
        if ($this->hasModelDescriptionForeignKey()) {
            Schema::table('ai_model_descriptions', static fn (Blueprint $table) => $table->dropForeign(['ai_model_id']));
        }

        if (Schema::hasColumn('ai_tools', 'access_rule')) {
            Schema::table('ai_tools', static function (Blueprint $table): void {
                // SQLite refuses to drop a column an index still references; MySQL would
                // drop the index implicitly, so dropping it first is correct on both.
                $table->dropIndex(['access_rule']);
                $table->dropColumn('access_rule');
            });
        }

        $this->dropExistingColumns('ai_providers', ['additional_config', 'icon']);

        foreach (self::ADMIN_MANAGED_TABLES as $name) {
            $this->dropExistingColumns($name, ['admin_managed']);
        }

        $this->dropExistingColumns('users', ['admin_disabled', 'last_login_at', 'local_password']);
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

    /**
     * SQLite only supports foreign keys declared at table creation and silently ignores the
     * alteration above, so on that driver this stays false and both up() and down() skip it.
     */
    private function hasModelDescriptionForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('ai_model_descriptions'))
            ->contains(static fn (array $key): bool => $key['columns'] === ['ai_model_id'] && $key['foreign_table'] === 'ai_models');
    }

    /**
     * @param list<string> $columns
     */
    private function dropExistingColumns(string $table, array $columns): void
    {
        $existing = array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn($table, $column)));
        if ($existing !== []) {
            Schema::table($table, static fn (Blueprint $blueprint) => $blueprint->dropColumn($existing));
        }
    }
};
