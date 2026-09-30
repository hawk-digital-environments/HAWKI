<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Services\Admin\Repositories\UserRepository;

final class UserSchema extends Schema
{
    protected const REPOSITORY = UserRepository::class;
    protected const ATTRIBUTES = [
        'name', 'username', 'email', 'employeetype', 'admin_disabled', 'last_login_at', 'local_account', 'is_system',
    ];
    public static string $model = Records\User::class;

    public static function type(): string
    {
        return 'admin-users';
    }
}
