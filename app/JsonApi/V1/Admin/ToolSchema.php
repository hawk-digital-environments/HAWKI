<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\ToolRepository;

final class ToolSchema extends Schema
{
    protected const REPOSITORY = ToolRepository::class;
    protected const ATTRIBUTES = [
        'name', 'kind', 'active', 'mapped_capability', 'description', 'models', 'access_rule',
    ];
    public static string $model = Records\Tool::class;

    public static function type(): string
    {
        return 'admin-tools';
    }
}
