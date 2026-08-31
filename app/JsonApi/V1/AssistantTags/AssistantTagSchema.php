<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantTags;

use App\Models\Assistants\AssistantTag;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AssistantTagSchema extends Schema
{
    public static string $model = AssistantTag::class;
    protected bool $selfLink = false;

    public static function type(): string
    {
        return 'assistant-tags';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('text'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
