<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi\Fixtures;

use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Schema;

class TestingSchema extends Schema
{
    public static string $model = Testing::class;

    public static function type(): string
    {
        return 'testing';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('name'),
            Str::make('status'),
            Number::make('max_count'),
            Boolean::make('is_active'),
            DateTime::make('created_at')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [
            Where::make('name'),
            Where::make('status'),
        ];
    }

    public function authorizable(): bool
    {
        return false;
    }
}
