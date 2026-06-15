<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantSettingValues;

use App\Models\Assistants\AssistantSettingValue;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AssistantSettingValueSchema extends Schema
{
    public static string $model = AssistantSettingValue::class;

    public static function type(): string
    {
        return 'assistant-setting-values';
    }

    protected bool $selfLink = false;

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('value'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            BelongsTo::make('assistant')->type('assistants'),
            BelongsTo::make('setting')->type('assistant-settings'),
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
