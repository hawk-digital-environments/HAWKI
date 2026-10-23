<?php

declare(strict_types=1);

namespace App\JsonApi\V1\SystemModels;

use App\Models\Ai\SystemModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Schema;

class SystemModelSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = SystemModel::class;

    /**
     * The model and its provider are needed to build the qualified `model_id`.
     */
    protected array $with = ['model.provider'];

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make('model_type'),
            Str::make('usage_type'),
            // Same provider-qualified format as `ai-models.model_id`, so clients can match both.
            Str::make('model_id')
                ->extractUsing(fn(SystemModel $systemModel) => $systemModel->model?->getIdentifier())
                ->readOnly(),
            BelongsTo::make('model')->type('ai-models')->readOnly(),
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
            Where::make('usage_type'),
            Where::make('model_type'),
            new SystemModelModelIdFilter(),
        ];
    }

    public function indexQuery(?Request $request, Builder $query): Builder
    {
        if (!$request->query->has('filter.usage_type')) {
            $request->query->set('filter.usage_type', $request->getUsageContext()->get());
        }
        return $query;
    }
}
