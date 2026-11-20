<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantVersions;

use App\Models\Assistants\AssistantVersion;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AssistantVersionSchema extends Schema
{
    public static string $model = AssistantVersion::class;
    protected bool $selfLink = false;

    public static function type(): string
    {
        return 'assistant-versions';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('text'),
            Number::make('version'),
            ArrayList::make('changed_keys'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
