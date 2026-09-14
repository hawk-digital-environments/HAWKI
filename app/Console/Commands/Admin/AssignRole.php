<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\RoleGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignRole extends Command
{
    protected $signature = 'rbac:grant {username} {role} {--revoke : Remove the manual grant instead}';
    protected $description = 'Grant or revoke a manual role assignment';

    public function handle(RoleGuard $guard, AdminAudit $audit): int
    {
        $user = User::withoutGlobalScopes()->where('username', $this->argument('username'))->firstOrFail();
        $role = DB::table('roles')->where('slug', $this->argument('role'))->first();

        if (!$role) {
            $this->error('Unknown role.');

            return self::FAILURE;
        }

        $guard->mutate(function () use ($user, $role, $audit): void {
            $key = ['user_id' => $user->id, 'role_id' => $role->id, 'source' => 'manual'];

            if ($this->option('revoke')) {
                DB::table('role_user')->where($key)->delete();
            } else {
                DB::table('role_user')->insertOrIgnore($key + ['created_at' => now()]);
            }

            $audit->record($this->option('revoke') ? 'revoke' : 'grant', 'roles', $role->id, ['user_id' => $user->id]);
        });
        $this->info('Role assignment saved. Employee-type assignments are unchanged.');

        return self::SUCCESS;
    }
}
