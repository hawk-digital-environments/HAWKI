<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\ModelRepository;

final class ModelSchema extends Schema
{
    protected const REPOSITORY = ModelRepository::class;
    protected const ATTRIBUTES = [
        'label', 'model_id', 'provider_id', 'active', 'status', 'descriptions', 'model_type', 'documentation_url', 'deprecation_date', 'input', 'output', 'parameters', 'native_capabilities', 'settings', 'limits', 'pricing', 'flags', 'tools', 'usage_rules',
    ];
    public static string $model = Records\Model::class;

    public static function type(): string
    {
        return 'admin-models';
    }
}
