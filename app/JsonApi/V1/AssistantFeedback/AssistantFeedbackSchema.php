<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantFeedback;

use App\Models\Assistants\AssistantFeedback;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class AssistantFeedbackSchema extends Schema
{
    public static string $model = AssistantFeedback::class;

    public static function type(): string
    {
        return 'assistant-feedback';
    }

    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('text'),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
            BelongsTo::make('assistant')->type('assistants'),
            BelongsTo::make('user')->type('users')->readOnly(),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
