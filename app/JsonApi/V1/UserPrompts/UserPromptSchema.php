<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserPrompts;

use App\Models\Assistants\UserPrompt;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class UserPromptSchema extends Schema
{
    public static string $model = UserPrompt::class;

    protected bool $selfLink = false;

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
