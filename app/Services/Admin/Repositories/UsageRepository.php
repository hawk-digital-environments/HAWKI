<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\Permission;
use App\Services\Admin\PermissionService;
use App\Services\Admin\UsageStatistics;

class UsageRepository extends ResourceRepository
{
    public const RESOURCE = 'usage';

    protected function readContent(User $user, array $filters): array
    {
        if ('user' === ($filters['group_by'] ?? '') || isset($filters['user'])) {
            app(PermissionService::class)->authorize($user, Permission::USAGE_PER_USER);
        }

        return app(UsageStatistics::class)->read($filters) + ['columns' => ['label', 'requests', 'prompt_tokens', 'completion_tokens']];
    }
}
