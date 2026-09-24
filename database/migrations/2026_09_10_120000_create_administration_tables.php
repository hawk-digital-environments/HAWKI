<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administration: the admin tables and columns on
 * `users` and the AI configuration tables.
 *
 * MySQL auto-commits DDL, so a crash between any two statements would leave the migration
 * unrecorded and otherwise unrepeatable. Every schema step is therefore guarded and every seed
 * ignores rows that already exist, which makes up() restartable and down() safe on a half-applied
 * schema.
 */
return new class() extends Migration {
    private const ADMIN_MANAGED_TABLES = [
        'ai_providers', 'ai_models', 'mcp_servers', 'ai_tools', 'system_models', 'system_prompts', 'ai_model_descriptions',
    ];

    public function up(): void
    {
        $this->createAdminTables();
        $this->addColumns();
    }

    public function down(): void
    {
        $this->dropColumns();

        foreach ([
            'usage_daily_totals', 'admin_deleted_records', 'admin_audit_log', 'admin_settings',
        ] as $table) {
            Schema::dropIfExists($table);
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

        $this->dropExistingColumns('ai_providers', ['additional_config', 'icon']);

        foreach (self::ADMIN_MANAGED_TABLES as $name) {
            $this->dropExistingColumns($name, ['admin_managed']);
        }

        $this->dropExistingColumns('users', ['admin_disabled', 'last_login_at', 'local_password']);
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
