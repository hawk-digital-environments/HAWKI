<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\RoleAssignmentService;
use App\Services\Admin\RoleGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignRole extends Command
{
    protected $signature = 'rbac:grant {username} {role} {--revoke : Remove the manual grant instead}';
    protected $description = 'Grant or revoke a manual role assignment';

    public function handle(RoleGuard $guard, AdminAudit $audit, RoleAssignmentService $assignments): int
    {
        $user = User::withoutGlobalScopes()->where('username', $this->argument('username'))->firstOrFail();
        $role = DB::table('roles')->where('name', $this->argument('role'))->where('guard_name', 'web')->first();

        if (!$role) {
            $this->error('Unknown role.');

            return self::FAILURE;
        }

        $guard->mutate(function () use ($user, $role, $audit, $assignments): void {
            $assignments->setManual($user, (int) $role->id, !$this->option('revoke'));

            $audit->record($this->option('revoke') ? 'revoke' : 'grant', 'roles', $role->id, null, null, ['user_id' => $user->id]);
        });
        $this->info('Role assignment saved. Employee-type assignments are unchanged.');

        return self::SUCCESS;
    }
}
