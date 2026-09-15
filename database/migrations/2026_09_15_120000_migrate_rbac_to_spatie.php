<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class() extends Migration {
    /**
     * Frozen copy of the administration permission names registered when this migration
     * was written. It is deliberately not read from App\Services\Admin\Permission: a later
     * edit of that enum must not retroactively change what this migration imports or what
     * it restores on rollback. The tools/AI capability names are created by the following
     * migrations instead, so they are absent here on purpose.
     */
    private const REGISTERED_PERMISSIONS = [
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
    private const CHUNK = 500;

    public function up(): void
    {
        // Preserve role IDs and every foreign key, including announcement targets.
        // MySQL auto-commits DDL, so a crash between any two steps below would otherwise
        // leave the migration unrecorded and unrepeatable. Every step is therefore guarded
        // and every backfill ignores rows that already exist, making up() restartable.
        if (Schema::hasColumn('roles', 'name') && !Schema::hasColumn('roles', 'display_name')) {
            Schema::table('roles', static fn (Blueprint $table) => $table->renameColumn('name', 'display_name'));
        }

        // Runs both after the rename above and after a crash in between the two renames,
        // where the table is left with `slug` and `display_name` and no `name`.
        if (Schema::hasColumn('roles', 'slug') && !Schema::hasColumn('roles', 'name')) {
            Schema::table('roles', static fn (Blueprint $table) => $table->renameColumn('slug', 'name'));
        }

        if (!Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', static fn (Blueprint $table) => $table->string('guard_name')->default('web'));
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

        DB::transaction(function (): void {
            $now = now();
            DB::table('permissions')->insertOrIgnore(array_map(
                static fn (string $name): array => [
                    'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
                ],
                self::REGISTERED_PERMISSIONS,
            ));

            $this->copyInChunks(
                'role_has_permissions',
                DB::table('role_permissions')
                    ->join('permissions', 'permissions.name', '=', 'role_permissions.permission')
                    ->where('permissions.guard_name', 'web')
                    ->whereIn('permissions.name', self::REGISTERED_PERMISSIONS)
                    ->orderBy('role_permissions.role_id')->orderBy('permissions.id')
                    ->select(['role_permissions.role_id as role_id', 'permissions.id as permission_id']),
            );
            $this->copyInChunks(
                'model_has_roles',
                DB::table('role_user')->distinct()
                    ->orderBy('role_id')->orderBy('user_id')
                    ->select(['role_id', 'user_id as model_id'])
                    ->selectRaw('? as model_type', [(new User())->getMorphClass()]),
            );
        });

        // Keep the legacy grants as a migration snapshot until rollback or later cleanup.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Rebuild the old grants from current state so rollback preserves admin edits.
        // Only names this migration imported are replaced; legacy rows naming a permission
        // that was never registered (or has since been retired) survive the rollback.
        if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
            DB::transaction(function (): void {
                DB::table('role_permissions')->whereIn('permission', self::REGISTERED_PERMISSIONS)->delete();
                $this->copyInChunks(
                    'role_permissions',
                    DB::table('role_has_permissions')
                        ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                        ->where('permissions.guard_name', 'web')
                        ->whereIn('permissions.name', self::REGISTERED_PERMISSIONS)
                        ->orderBy('role_has_permissions.role_id')->orderBy('permissions.name')
                        ->select(['role_has_permissions.role_id as role_id', 'permissions.name as permission']),
                );
            });
        }

        foreach (['model_has_permissions', 'model_has_roles', 'role_has_permissions', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasColumn('roles', 'name') && !Schema::hasColumn('roles', 'slug')) {
            Schema::table('roles', static fn (Blueprint $table) => $table->renameColumn('name', 'slug'));
        }

        if (Schema::hasColumn('roles', 'display_name') && !Schema::hasColumn('roles', 'name')) {
            Schema::table('roles', static fn (Blueprint $table) => $table->renameColumn('display_name', 'name'));
        }

        if (Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', static fn (Blueprint $table) => $table->dropColumn('guard_name'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Copy a select into another table without ever failing on rows that are already there.
     * insertUsing() has no ignore variant, so the rows are read in ordered pages and written
     * with insertOrIgnore(), which compiles to `INSERT IGNORE` on MySQL and `INSERT OR IGNORE`
     * on SQLite. Both drivers then silently skip rows that collide with the target's primary
     * key, which is what makes a partially completed backfill safe to retry.
     */
    private function copyInChunks(string $table, QueryBuilder $source): void
    {
        foreach ($source->lazy(self::CHUNK)->chunk(self::CHUNK) as $chunk) {
            DB::table($table)->insertOrIgnore($chunk->map(static fn ($row): array => (array) $row)->values()->all());
        }
    }
};
