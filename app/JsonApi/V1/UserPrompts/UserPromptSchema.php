<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserPrompts;

use App\Models\Assistants\UserPrompt;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class UserPromptSchema extends Schema
{
    public static string $model = UserPrompt::class;

    protected bool $selfLink = false;

    public static function type(): string
    {
        return 'assistant-user-prompts';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('text'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            BelongsTo::make('assistant')->type('assistants'),
        ];
    }

    public function filters(): array
    {
        return [];
    }

    public function authorizable(): bool
    {
        return false;
    }
}
