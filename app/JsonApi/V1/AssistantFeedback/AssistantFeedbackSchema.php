<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantFeedback;

use App\Models\Assistants\AssistantFeedback;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Resources\Relation;
use LaravelJsonApi\Eloquent\Schema;

class AssistantFeedbackSchema extends Schema
{
    public static string $model = AssistantFeedback::class;
    
    protected int $maxDepth = 2;

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
            // Write-only relationships: settable on create but not exposed as read
            // endpoints, so the self/related links dont show.
            BelongsTo::make('assistant')->type('assistants')
                ->serializeUsing(static function (Relation $relation): void {
                    $relation->withoutSelfLink()->withoutRelatedLink();
                }),
            BelongsTo::make('user')->type('users')->readOnly()
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
