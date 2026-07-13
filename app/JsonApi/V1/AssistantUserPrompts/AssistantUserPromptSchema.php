<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantUserPrompts;

use App\Models\Assistants\AssistantUserPrompt;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AssistantUserPromptSchema extends Schema
{
    public static string $model = AssistantUserPrompt::class;
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
}
