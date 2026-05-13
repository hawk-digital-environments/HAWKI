<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Users;

use App\Models\User;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class UserSchema extends Schema
{
    public static string $model = User::class;

    protected bool $selfLink = false;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name'),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
