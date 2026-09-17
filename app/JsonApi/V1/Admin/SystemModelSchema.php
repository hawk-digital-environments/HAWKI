<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\SystemModelRepository;

final class SystemModelSchema extends Schema
{
    protected const REPOSITORY = SystemModelRepository::class;
    protected const ATTRIBUTES = [
        'model_type', 'usage_type', 'model_id', 'prompts',
    ];
    public static string $model = Records\SystemModel::class;

    public static function type(): string
    {
        return 'admin-system-models';
    }
}
