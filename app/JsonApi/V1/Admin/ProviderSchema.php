<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\ProviderRepository;

final class ProviderSchema extends Schema
{
    protected const REPOSITORY = ProviderRepository::class;
    protected const ATTRIBUTES = [
        'name', 'provider_id', 'adapter_key', 'active', 'api_key_set', 'api_url', 'model_status_url', 'additional_config_set', 'settings', 'icon', 'icon_url', 'icon_url_dark',
    ];
    public static string $model = Records\Provider::class;

    public static function type(): string
    {
        return 'admin-providers';
    }
}
