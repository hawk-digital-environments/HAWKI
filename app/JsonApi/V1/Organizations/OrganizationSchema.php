<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Organizations;

use App\Models\Organization;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class OrganizationSchema extends Schema
{
    public static string $model = Organization::class;
    protected bool $selfLink = false;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
