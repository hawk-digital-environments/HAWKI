<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Storage\Interfaces\FileInterface;
use Laravel\Ai\Contracts\Agent;

abstract class AbstractProviderAdapter implements ProviderAdapterInterface
{
    use ModelInfoEnrichingTrait;

    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void
    {
        $statusCollection->setAllUnknown();
    }

    public function getNameLabel(): string|null
    {
        return null;
    }

    public function getDescriptionLabel(): string|null
    {
        return null;
    }

    public function getNativeToolFactoryForCapability(string $capability): \Closure|null
    {
        return null;
    }

    public function getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array
    {
        return [];
    }

    public function supportsFileAsAttachment(FileInterface $file): bool
    {
        return true;
    }

    protected function assertNonEmptyApiUrl(string|null $apiUrl, AgentRequestContext|AiProvider $context): string
    {
        if (empty($apiUrl)) {
            $provider = $context instanceof AgentRequestContext ? $context->provider : $context;
            throw InvalidProviderConfigurationException::forMissingApiUrl($provider->name, $provider->adapter_key);
        }

        return $apiUrl;
    }

    protected function createModelListClient(AiProviderProxy $provider): ModelListClient
    {
        return new ModelListClient(fn() => $this->createHttpClient($provider));
    }
}
