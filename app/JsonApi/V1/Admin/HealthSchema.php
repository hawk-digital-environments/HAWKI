<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\HealthRepository;

final class HealthSchema extends Schema
{
    protected const REPOSITORY = HealthRepository::class;
    protected const ID_PATTERN = '[^/]+';
    protected const ATTRIBUTES = [
        'name', 'status', 'message', 'response_time',
    ];
    public static string $model = Records\Health::class;

    public static function type(): string
    {
        return 'admin-health';
    }
}
