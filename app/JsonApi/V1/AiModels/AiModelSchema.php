<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AiModels;

use App\Models\Ai\AiModel;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Limits\AiModelLimitsInterface;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\AiModelPricingInterface;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

class AiModelSchema extends Schema
{
    use ServiceLocatorTrait;

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
            Str::make('model_type'),
            Str::make('label'),
            ArrayList::make('input')
                ->serializeUsing(fn(AiModelIoMethods $methods) => $methods->toArray()),
            ArrayList::make('output')
                ->serializeUsing(fn(AiModelIoMethods $methods) => $methods->toArray()),
            ArrayHash::make('parameters')
                ->serializeUsing(fn(AiModelParameters $parameters) => $parameters->toArray()),
            Str::make('status'),
            Str::make('demand'),
            ArrayHash::make('native_capabilities')
                ->extractUsing(function (AiModel $model) {
                    // Since there could be a discrepancy between the capabilities defined in the model
                    // and the actual capabilities supported by the provider adapter, we filter them here.
                    $adapter = $this->getService(AiProviderProxyResolver::class)->resolveForModel($model)->adapter;
                    return array_values(
                        array_filter(
                            $model->native_capabilities->toArray(),
                            static function (string $capability) use ($adapter) {
                                return $adapter->getNativeToolFactoryForCapability($capability) !== null;
                            }
                        )
                    );
                })
                ->serializeUsing(function (NativeAiModelCapabilities $capabilities) {
                    return $capabilities->toArray();
                }),
            ArrayHash::make('settings')
                ->serializeUsing(fn(AiModelSettings $settings) => $settings->toArray()),
            ArrayHash::make('limits')
                ->serializeUsing(fn(AiModelLimitsInterface $limits) => $limits->toArray()),
            ArrayHash::make('pricing')
                ->serializeUsing(function (AiModelPricingInterface $pricing) {
                    if ($pricing->isFree()) {
                        return ['free' => true];
                    }
                    if ($pricing->isUndefined()) {
                        return null;
                    }
                    return $pricing->toArray();
                }),
            ArrayHash::make('flags')
                ->serializeUsing(fn(AiModelFlags $flags) => $flags->toArray()),
            DateTime::make('created_at')->readOnly(),
            DateTime::make('updated_at')->readOnly(),
            ArrayList::make('tool_ids')
                ->extractUsing(fn($model) => $model->tools()->pluck('ai_tools.id')->toArray())->readOnly(),
            BelongsTo::make('provider')->type('ai-providers')->readOnly(),
            BelongsToMany::make('tools')->type('ai-tools')->readOnly(),
            HasMany::make('description')
                ->type('ai-model-descriptions')
                ->readOnly(),
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
