<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class() extends Migration {
    /**
     * Frozen list of the tool/AI capability permission names introduced with access rules.
     * The RBAC migration imports only the administration names, so these rows are created
     * here for the first time. Written with the query builder rather than Spatie's Permission
     * model so the migration depends on neither the model class nor the registrar cache.
     */
    private const TOOL_PERMISSIONS = [
        'tools.use',
        'ai.capabilities.web_search.use',
        'ai.capabilities.image_generation.use',
        'tools.internal_search.use',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('ai_tools', 'access_rule')) {
            Schema::table('ai_tools', static function (Blueprint $table): void {
                $table->string('access_rule', 80)->default('unavailable')->index();
            });
        }

        // Intentionally grant no existing role new access. Administrators explicitly publish
        // rules and grants. insertOrIgnore keeps a retried migration a no-op on MySQL and SQLite.
        $now = now();
        DB::table('permissions')->insertOrIgnore(array_map(
            static fn (string $name): array => [
                'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ],
            self::TOOL_PERMISSIONS,
        ));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The permission rows stay: rolling this migration back must not silently revoke
        // grants an administrator published. The RBAC migration's down() drops the table.
        if (Schema::hasColumn('ai_tools', 'access_rule')) {
            Schema::table('ai_tools', static function (Blueprint $table): void {
                // SQLite refuses to drop a column an index still references; MySQL would
                // drop the index implicitly, so dropping it first is correct on both.
                $table->dropIndex(['access_rule']);
                $table->dropColumn('access_rule');
            });
        }
    }
};
