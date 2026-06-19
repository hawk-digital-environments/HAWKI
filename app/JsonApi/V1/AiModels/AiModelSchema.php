<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiModels;

use App\Models\Ai\AiModel;
use App\Services\Ai\Values\ModelCapabilities;
use App\Services\Ai\Values\ModelIoMethods;
use App\Services\Ai\Values\ModelParameters;
use App\Services\Ai\Values\ModelSettings;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
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

    public function fields(): array
    {
        return [
            ID::make(),
            Boolean::make('active'),
            Str::make('model_id'),
            Str::make('label'),
            ArrayList::make('input')
                ->serializeUsing(fn(ModelIoMethods $methods) => $methods->toArray()),
            ArrayList::make('output')
                ->serializeUsing(fn(ModelIoMethods $methods) => $methods->toArray()),
            ArrayHash::make('parameters')
                ->serializeUsing(fn(ModelParameters $parameters) => $parameters->toArray()),
            Str::make('status'),
            Str::make('demand'),
            ArrayHash::make('capabilities')
                ->serializeUsing(fn(ModelCapabilities $capabilities) => $capabilities->toArray()),
            ArrayHash::make('settings')
                ->serializeUsing(fn(ModelSettings $settings) => $settings->toArray()),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
            ArrayList::make('tool_ids')->extractUsing(fn($model) => $model->tools()->pluck('ai_tools.id')->toArray())->readOnly(),

            BelongsTo::make('provider')->type('ai-providers')->readOnly(),
            BelongsToMany::make('tools')->type('ai-tools')->readOnly(),
        ];
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
