<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\SettingRepository;

final class SettingSchema extends Schema
{
    protected const REPOSITORY = SettingRepository::class;
    protected const ID_PATTERN = '[^/]+';
    protected const ATTRIBUTES = [
        'key', 'value', 'default', 'kind', 'options', 'source',
    ];
    public static string $model = Records\Setting::class;

    public static function type(): string
    {
        return 'admin-settings';
    }
}
