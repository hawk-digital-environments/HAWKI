<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Factories;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Chat\Factories\Contracts\ChatAgentFactoryInterface;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use App\Services\System\UsageTypes\UsageContext;

/**
 * Shared base for all chat agent factories working on the {@see \App\Services\Ai\Chat\Values\AiRequest} IR.
 *
 * Mirrors the former legacy agent factory base: the three
 * cross-cutting services (tool resolution, provider-proxy resolution, usage-type
 * determination) are injected via public setters by the container's `afterResolving`
 * callback registered in {@see \App\Providers\AiServiceProvider}, with an `app()` fallback
 * for factory instances created outside the normal resolution path.
 */
abstract class AbstractChatAgentFactory implements ChatAgentFactoryInterface
{
    private ?LaravelToolResolver $toolResolver = null;
    private ?AiProviderProxyResolver $providerProxyResolver = null;
    private ?UsageContext $usageContext = null;

    /**
     * Injects the tool resolver. Called automatically by the container's afterResolving hook.
     */
    final public function setToolResolver(LaravelToolResolver $toolResolver): void
    {
        $this->toolResolver = $toolResolver;
    }

    /**
     * Injects the provider-proxy resolver. Called automatically by the container's afterResolving hook.
     */
    final public function setProviderProxyResolver(AiProviderProxyResolver $providerProxyResolver): void
    {
        $this->providerProxyResolver = $providerProxyResolver;
    }

    /**
     * Injects the usage context. Called automatically by the container's afterResolving hook.
     */
    final public function setUsageContext(UsageContext $usageContext): void
    {
        $this->usageContext = $usageContext;
    }

    protected function getToolResolver(): LaravelToolResolver
    {
        return $this->toolResolver ??= app(LaravelToolResolver::class);
    }

    protected function getProviderProxyResolver(): AiProviderProxyResolver
    {
        return $this->providerProxyResolver ??= app(AiProviderProxyResolver::class);
    }

    protected function getUsageContext(): UsageContext
    {
        return $this->usageContext ??= app(UsageContext::class);
    }

    /**
     * Assembles an {@see AgentRequestContext} from a model and optional overrides.
     *
     * When $parameters is omitted the model's own stored defaults are used. $usageType is
     * normalised through {@see UsageContext::getForGiven()}; $formatKey, $usageRecordedViaListener,
     * $channel and $roomId carry the proxy-channel metadata used by the usage
     * recording listener.
     */
    protected function createRequestContext(
        AiModel $model,
        ?AiModelParameters $parameters = null,
        ?AiProvider $provider = null,
        ?string $usageType = null,
        ?string $formatKey = null,
        bool $usageRecordedViaListener = false,
        string $channel = 'chat',
        ?int $roomId = null,
    ): AgentRequestContext {
        $parameters ??= AiModelParameters::fromArray($model->parameters->toArray());
        $providerProxy = null === $provider
            ? $this->getProviderProxyResolver()->resolveForModel($model)
            : $this->getProviderProxyResolver()->resolve($provider);

        return new AgentRequestContext(
            provider: $providerProxy,
            model: $model,
            modelParameters: $parameters,
            usageType: $this->getUsageContext()->getForGiven($usageType),
            formatKey: $formatKey,
            usageRecordedViaListener: $usageRecordedViaListener,
            channel: $channel,
            roomId: $roomId,
        );
    }
}
