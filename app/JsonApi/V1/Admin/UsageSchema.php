<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\UsageRepository;

final class UsageSchema extends Schema
{
    protected const REPOSITORY = UsageRepository::class;
    protected const ID_PATTERN = '[^/]+';
    protected const ATTRIBUTES = [
        'label', 'requests', 'prompt_tokens', 'completion_tokens',
    ];
    public static string $model = Records\Usage::class;

    public static function type(): string
    {
        return 'admin-usage';
    }
}
