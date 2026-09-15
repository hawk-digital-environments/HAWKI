<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\RoleMappingRepository;

final class RoleMappingSchema extends Schema
{
    protected const REPOSITORY = RoleMappingRepository::class;
    protected const ATTRIBUTES = [
        'employee_type', 'role_id',
    ];
    public static string $model = Records\RoleMapping::class;

    public static function type(): string
    {
        return 'admin-mappings';
    }
}
