<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\RoleRepository;

final class RoleSchema extends Schema
{
    protected const REPOSITORY = RoleRepository::class;
    protected const ATTRIBUTES = [
        'name', 'slug', 'description', 'is_system', 'permissions',
    ];
    public static string $model = Records\Role::class;

    public static function type(): string
    {
        return 'admin-roles';
    }
}
