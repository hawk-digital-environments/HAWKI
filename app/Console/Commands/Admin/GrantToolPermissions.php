<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Models\Role;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\RoleGuard;
use App\Services\Ai\Tools\ToolAccessRules;
use Illuminate\Console\Command;

/** Explicit operator bootstrap for grants that no existing administrator can yet delegate. */
final class GrantToolPermissions extends Command
{
    protected $signature = 'rbac:grant-tool-access {role : Stable role slug} {rule : web_search, web_fetch, image_generation or internal_search}';
    protected $description = 'Explicitly grant a tool access rule to a role; tools still require a published access rule';

    public function handle(RoleGuard $guard, AdminAudit $audit): int
    {
        $rule = $this->argument('rule');
        $permissions = ToolAccessRules::RULES[$rule] ?? [];
        if (!$permissions) {
            $this->error('Unknown or unavailable tool access rule.');
            return self::FAILURE;
        }
        $guard->mutate(function () use ($permissions, $audit, $rule): void {
            $role = Role::query()->where('name', $this->argument('role'))->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            $role->givePermissionTo($permissions);
            $audit->record('grant-tool-access', 'roles', $role->id, null, null, ['rule' => $rule, 'permissions' => $permissions]);
        });
        $this->info('Role grants saved. Publish the tool access rule in Administration to enable use.');
        return self::SUCCESS;
    }
}
