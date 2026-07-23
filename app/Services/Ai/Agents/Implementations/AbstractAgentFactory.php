<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Implementations;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use App\Services\System\UsageTypes\UsageContext;

/**
 * Shared base for all HAWKI agent factories, providing lazy-loaded access to the three
 * cross-cutting services every factory needs: tool resolution, provider-proxy resolution,
 * and usage-type determination.
 *
 * Each service is injected via a public setter by the container's `afterResolving` callback
 * registered in {@see \App\Providers\AiServiceProvider}. A container fallback (via `app()`)
 * is kept as a safety net for factory instances created outside the normal resolution path,
 * but in practice all three setters are called before `createAgent()` is invoked.
 *
 * The primary utility for subclasses is {@see createRequestContext()}, which assembles the
 * fully-resolved {@see AgentRequestContext} from a model and optional parameter/provider overrides.
 */
abstract class AbstractAgentFactory implements AgentFactoryInterface
{
    private LaravelToolResolver|null $toolResolver = null;
    private AiProviderProxyResolver|null $providerProxyResolver = null;
    private UsageContext|null $usageContext = null;

    /**
     * Injects the tool resolver. Called automatically by the container's afterResolving hook.
     */
    public function setToolResolver(LaravelToolResolver $toolResolver): void
    {
        $this->toolResolver = $toolResolver;
    }

    protected function getToolResolver(): LaravelToolResolver
    {
        return $this->toolResolver ??= app(LaravelToolResolver::class);
    }

    /**
     * Injects the provider-proxy resolver. Called automatically by the container's afterResolving hook.
     */
    public function setProviderProxyResolver(AiProviderProxyResolver $providerProxyResolver): void
    {
        $this->providerProxyResolver = $providerProxyResolver;
    }

    protected function getProviderProxyResolver(): AiProviderProxyResolver
    {
        return $this->providerProxyResolver ??= app(AiProviderProxyResolver::class);
    }

    /**
     * Injects the usage context. Called automatically by the container's afterResolving hook.
     */
    public function setUsageContext(UsageContext $usageContext): void
    {
        $this->usageContext = $usageContext;
    }

    protected function getUsageContext(): UsageContext
    {
        return $this->usageContext ??= app(UsageContext::class);
    }

    /**
     * Assembles an {@see AgentRequestContext} from a model and optional overrides.
     *
     * When $parameters is omitted the model's own stored defaults are used. When $provider is
     * omitted the proxy resolver picks the provider that is configured for the model. The
     * $usageType is normalised through {@see UsageContext::getForGiven()} so that null becomes
     * the application-wide default usage type.
     */
    protected function createRequestContext(
        AiModel            $model,
        ?AiModelParameters $parameters = null,
        ?AiProvider        $provider = null,
        ?string            $usageType = null
    ): AgentRequestContext
    {
        $parameters = $parameters ?? AiModelParameters::fromArray($model->parameters->toArray());
        $providerProxy = $provider === null
            ? $this->getProviderProxyResolver()->resolveForModel($model)
            : $this->getProviderProxyResolver()->resolve($provider);

        $usageType = $this->getUsageContext()->getForGiven($usageType);

        return new AgentRequestContext(
            provider: $providerProxy,
            model: $model,
            modelParameters: $parameters,
            usageType: $usageType
        );
    }
}
