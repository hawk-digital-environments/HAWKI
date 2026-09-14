<?php
declare(strict_types=1);

use App\Services\Admin\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->primary(['role_id', 'permission']);
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('manual');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['role_id', 'user_id', 'source']);
        });
        Schema::create('employee_type_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('employee_type')->unique();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('admin_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('resource_type');
            $table->string('resource_id')->nullable();
            $table->json('changes');
            $table->ipAddress('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('usage_daily_totals', function (Blueprint $table) {
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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('admin_disabled')->default(false);
            $table->timestamp('last_login_at')->nullable();
        });
        Schema::table('announcements', function (Blueprint $table) {
            $table->json('content')->nullable();
            $table->json('target_roles')->nullable();
            $table->boolean('is_published')->default(true);
        });
        foreach (['ai_providers', 'ai_models', 'mcp_servers', 'ai_tools', 'system_models', 'system_prompts', 'ai_model_descriptions'] as $name) {
            Schema::table($name, fn(Blueprint $table) => $table->boolean('admin_managed')->default(false));
        }

        $now = now();
        $admin = DB::table('roles')->insertGetId(['slug' => 'admin', 'name' => 'Administrator', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('roles')->insert(['slug' => 'user', 'name' => 'User', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('role_permissions')->insert(array_map(fn($permission) => ['role_id' => $admin, 'permission' => $permission], Permission::values()));
        DB::table('employee_type_role_mappings')->insert(['employee_type' => 'admin', 'role_id' => $admin, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('users')->where('employeetype', 'admin')->orderBy('id')->chunkById(500, function ($users) use ($admin, $now) {
            DB::table('role_user')->insert($users->map(fn($user) => ['role_id' => $admin, 'user_id' => $user->id, 'source' => 'manual', 'created_at' => $now])->all());
        });
    }

    public function down(): void
    {
        foreach (['ai_providers', 'ai_models', 'mcp_servers', 'ai_tools', 'system_models', 'system_prompts', 'ai_model_descriptions'] as $name) {
            Schema::table($name, fn(Blueprint $table) => $table->dropColumn('admin_managed'));
        }
        Schema::table('announcements', fn(Blueprint $table) => $table->dropColumn(['content', 'target_roles', 'is_published']));
        Schema::table('users', fn(Blueprint $table) => $table->dropColumn(['admin_disabled', 'last_login_at']));
        foreach (['usage_daily_totals', 'admin_audit_log', 'admin_settings', 'employee_type_role_mappings', 'role_user', 'role_permissions', 'roles'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
