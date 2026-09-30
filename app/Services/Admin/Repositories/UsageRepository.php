<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\AdministrationAccess;
use App\Services\Admin\UsageStatistics;

class UsageRepository extends ResourceRepository
{
    public const RESOURCE = 'usage';

    protected function readContent(User $user, array $filters): array
    {
        if ('user' === ($filters['group_by'] ?? '') || isset($filters['user'])) {
            app(AdministrationAccess::class)->authorize($user);
        }

        return app(UsageStatistics::class)->read($filters) + ['columns' => ['label', 'requests', 'prompt_tokens', 'completion_tokens']];
    }
}
