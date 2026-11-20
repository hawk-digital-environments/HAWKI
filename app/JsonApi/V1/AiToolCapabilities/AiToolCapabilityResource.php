<?php

namespace App\JsonApi\V1\AiToolCapabilities;

use App\Models\Ai\AiModel;
use App\Services\Ai\Models\Capabilities\Values\AiModelCapabilityDefinition;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\System\JsonApi\ValueSerializer;
use App\Services\System\UsageTypes\UsageContext;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property AiModelCapabilityDefinition $resource
 */
class AiToolCapabilityResource extends JsonApiResource
{
    /**
     * Returns a unique id to identify this resource
     */
    public function id(): string
    {
        return $this->resource->key;
    }

    /**
     * Get the resource's attributes.
     *
     * @param Request|null $request
     */
    public function attributes($request): iterable
    {
        return [
            'native_model_ids' => $this->nativeModelIds($this->resource->key),
            'title_label' => $this->resource->titleLabel,
            'description_label' => $this->resource->descriptionLabel,
            'icon_path' => ValueSerializer::localFileAsDataUrl($this->resource->iconPath),
        ];
    }

    /**
     * Models that can run this capability through their provider's native
     * tool implementation (active model and provider, tools enabled).
     *
     * @return list<string>
     */
    private function nativeModelIds(string $capability): array
    {
        $ids = [];
        $usageType = app(UsageContext::class)->getForGiven(null);
        $models = AiModel::query()
            ->whereHas('usageRules', static fn ($rules) => $rules->where('usage_type', $usageType))
            ->get();

        foreach ($models as $model) {
            if (!$model->active || !$model->settings->canUseTools()
                || !$model->settings->canUseNativeCapabilities() || !$model->native_capabilities->has($capability)) {
                continue;
            }
            $provider = app(AiProviderProxyResolver::class)->resolveForModel($model);
            if ($provider->active && $provider->adapter->getNativeToolFactoryForCapability($capability)) {
                $ids[] = (string) $model->getKey();
            }
        }

        return $ids;
    }

    /**
     * Get the resource's relationships.
     *
     * @param Request|null $request
     */
    public function relationships($request): iterable
    {
        return [
        ];
    }
}
