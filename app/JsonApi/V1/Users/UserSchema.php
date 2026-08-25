<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Users;

use App\Models\User;
use App\Services\Storage\Values\StoredFileIdentifier;
use App\Services\Users\UserCondition;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class UserSchema extends Schema
{
    public static string $model = User::class;

    protected ?array $defaultPagination = ['number' => 1];

    public function fields(): array
    {
        return [
            ID::make(),
            // Identity/admin-only fields remain read-only so profile PATCHes
            // cannot alter them.
            Str::make('name')->extractUsing(function (User $user) {
                $displayName = $user->name ?: $user->username;

                // For some reason the HAWKI user has their name and username reversed.
                if ($user->id === 1) {
                    $displayName = $user->username;
                }

                return $displayName;
            }),
            Str::make('username')->readOnly(),
            Str::make('email')->hidden(UserCondition::isNonAdmin(...))->readOnly(),
            Str::make('bio'),
            Str::make('avatar')->extractUsing(function (User $user) {
                $identifier = StoredFileIdentifier::tryFromUserAvatar($user);
                return $identifier;
            })->readOnly(),
            Str::make('employee_type')->hidden(UserCondition::isNonAdmin(...))->readOnly(),
            Str::make('created_at')->readOnly(),
            Str::make('updated_at')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
