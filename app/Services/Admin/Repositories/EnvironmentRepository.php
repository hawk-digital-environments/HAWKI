<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use App\Services\Admin\SystemSettings;

class EnvironmentRepository extends ResourceRepository
{
    public const RESOURCE = 'environment';

    protected function readContent(User $user, array $filters): array
    {
        return ['rows' => app(SystemSettings::class)->environment(), 'columns' => ['key', 'value', 'source']];
    }
}
