<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantUserPrompts;

use App\Models\Assistants\AssistantUserPrompt;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Resources\Relation;
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
            // Write-only relationship: settable on create but not exposed as a read
            // endpoint, so the self/related links are suppressed to avoid dead URLs.
            BelongsTo::make('assistant')->type('assistants')
                ->serializeUsing(static function (Relation $relation): void {
                    $relation->withoutSelfLink()->withoutRelatedLink();
                }),
        ];
    }

    public function filters(): array
    {
        return [];
    }
}
