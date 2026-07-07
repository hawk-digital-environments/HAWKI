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

abstract class AbstractAgentFactory implements AgentFactoryInterface
{
    private LaravelToolResolver|null $toolResolver = null;
    private AiProviderProxyResolver|null $providerProxyResolver = null;
    private UsageContext|null $usageContext = null;

    public function setToolResolver(LaravelToolResolver $toolResolver): void
    {
        $this->toolResolver = $toolResolver;
    }

    protected function getToolResolver(): LaravelToolResolver
    {
        return $this->toolResolver ??= app(LaravelToolResolver::class);
    }

    public function setProviderProxyResolver(AiProviderProxyResolver $providerProxyResolver): void
    {
        $this->providerProxyResolver = $providerProxyResolver;
    }

    protected function getProviderProxyResolver(): AiProviderProxyResolver
    {
        return $this->providerProxyResolver ??= app(AiProviderProxyResolver::class);
    }

    public function setUsageContext(UsageContext $usageContext): void
    {
        $this->usageContext = $usageContext;
    }

    protected function getUsageContext(): UsageContext
    {
        return $this->usageContext ??= app(UsageContext::class);
    }

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
