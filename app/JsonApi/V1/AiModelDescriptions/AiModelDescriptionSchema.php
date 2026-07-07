<?php

namespace App\JsonApi\V1\AiModelDescriptions;

use App\Models\Ai\AiModelDescription;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Schema;

class AiModelDescriptionSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = AiModelDescription::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Number::make('ai_model_id'),
            Str::make('locale'),
            Str::make('description'),

            BelongsTo::make('model', 'aiModel')->type('ai-models')->readOnly()
        ];
    }

    /**
     * Get the resource filters.
     *
     * @return array
     */
    public function filters(): array
    {
        return [
            Where::make('locale'),
            Where::make('ai_model_id')
        ];
    }
}
