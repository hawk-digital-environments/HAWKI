<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\EnvironmentRepository;

final class EnvironmentSchema extends Schema
{
    protected const REPOSITORY = EnvironmentRepository::class;
    protected const ID_PATTERN = '[^/]+';
    protected const ATTRIBUTES = [
        'key', 'value', 'source',
    ];
    public static string $model = Records\Environment::class;

    public static function type(): string
    {
        return 'admin-environment';
    }
}
