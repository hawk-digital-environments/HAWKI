<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiModels;

use App\Models\Ai\AiModel;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasOne;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AiModelSchema extends Schema
{
    public static string $model = AiModel::class;

    public static function type(): string
    {
        return 'ai-models';
    }

    protected bool $selfLink = false;

    public function fields(): array
    {
        return [
            ID::make(),
            Boolean::make('active'),
            Str::make('model_id'),
            Str::make('label'),
            ArrayList::make('input'),
            ArrayList::make('output'),
            ArrayHash::make('tools'),
            ArrayHash::make('default_params'),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),

            BelongsTo::make('provider')->type('ai-providers')->readOnly(),
            BelongsToMany::make('assignedTools', 'assignedTools')->type('ai-tools')->readOnly(),
            HasOne::make('status')->type('ai-model-statuses')->readOnly(),
        ];
    }

    public function authorizable(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return [];
    }

    public function pagination(): ?PagePagination
    {
        return PagePagination::make();
    }
}
