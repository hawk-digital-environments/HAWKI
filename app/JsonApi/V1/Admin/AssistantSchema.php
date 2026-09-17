<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\AssistantRepository;

final class AssistantSchema extends Schema
{
    protected const REPOSITORY = AssistantRepository::class;
    protected const ATTRIBUTES = [
        'name', 'handle', 'creator', 'status', 'version', 'based_on', 'created_at', 'updated_at', 'is_draft',
    ];
    public static string $model = Records\Assistant::class;

    public static function type(): string
    {
        return 'admin-assistants';
    }
}
