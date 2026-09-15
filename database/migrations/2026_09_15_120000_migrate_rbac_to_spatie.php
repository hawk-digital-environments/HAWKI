<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Admin\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class() extends Migration {
    public function up(): void
    {
        // Preserve role IDs and every foreign key, including announcement targets.
        Schema::table('roles', static function (Blueprint $table): void {
            $table->renameColumn('name', 'display_name');
            $table->renameColumn('slug', 'name');
            $table->string('guard_name')->default('web');
        });
        Schema::create('permissions', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('role_has_permissions', static function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('model_has_roles', static function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        // Required by HasRoles; HAWKI only authorizes grants inherited from roles.
        Schema::create('model_has_permissions', static function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        DB::transaction(static function (): void {
            foreach (Permission::values() as $name) {
                DB::table('permissions')->insert([
                    'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::table('role_has_permissions')->insertUsing(
                ['role_id', 'permission_id'],
                DB::table('role_permissions')->join('permissions', 'permissions.name', '=', 'role_permissions.permission')
                    ->select('role_permissions.role_id', 'permissions.id'),
            );
            DB::table('model_has_roles')->insertUsing(
                ['role_id', 'model_id', 'model_type'],
                DB::table('role_user')->select('role_id', 'user_id')->selectRaw('?', [(new User())->getMorphClass()])->distinct(),
            );
        });

        // Keep the legacy grants as a migration snapshot until rollback or later cleanup.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Rebuild the old grants from current state so rollback preserves admin edits.
        DB::transaction(static function (): void {
            DB::table('role_permissions')->delete();
            DB::table('role_permissions')->insertUsing(
                ['role_id', 'permission'],
                DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->where('permissions.guard_name', 'web')->whereIn('permissions.name', Permission::values())
                    ->select('role_has_permissions.role_id', 'permissions.name'),
            );
        });

        foreach (['model_has_permissions', 'model_has_roles', 'role_has_permissions', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('roles', static function (Blueprint $table): void {
            $table->renameColumn('name', 'slug');
            $table->renameColumn('display_name', 'name');
            $table->dropColumn('guard_name');
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
