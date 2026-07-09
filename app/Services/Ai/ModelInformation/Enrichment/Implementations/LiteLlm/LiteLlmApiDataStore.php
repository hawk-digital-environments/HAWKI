<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\System\Time\Clock;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;

/**
 * Fetches LiteLLM model data from the live public API, caching responses for 24 hours.
 *
 * @see StaticLiteLlmDataStore for the offline fallback used when the API is unavailable.
 */
class LiteLlmApiDataStore extends AbstractLiteLlmDataStore
{
    public function __construct(
        private readonly LiteLlmApiClient             $apiClient,
        private readonly Repository                   $cache,
        LoggerInterface                      $logger,
        LiteLlmDriverNameProviderNameMapping $nameMapping,
        private readonly Clock                        $clock = new Clock()
    )
    {
        parent::__construct($logger, $nameMapping);
    }

    /**
     * Evicts the cached API response for a provider, forcing a fresh request on the next access.
     */
    public function clearProviderModelInformationCache(AiProviderProxy $provider): void
    {
        $cacheKey = $this->getProviderModelInformationCacheKey($provider);
        $this->cache->forget($cacheKey);
    }

    /**
     * Loads model data from cache when available; otherwise fetches from the LiteLLM API
     * and caches the result for 24 hours.
     */
    protected function getProviderModelInformationList(AiProviderProxy $provider): array
    {
        $cacheKey = $this->getProviderModelInformationCacheKey($provider);

        $raw = $this->cache->remember(
            $cacheKey,
            $this->clock->now()->addHours(24),
            function () use ($provider) {
                $providerName = $this->nameMapping->getProviderNameFromProxy($provider);
                // Fetch model information from the API client
                return [...$this->apiClient->fetchData($providerName)];
            });

        return $this->loadModelDataList($provider, $raw);
    }

    private function getProviderModelInformationCacheKey(AiProviderProxy $provider): string
    {
        $providerName = $this->nameMapping->getProviderNameFromProxy($provider);
        return "lite_llm_model_information_$providerName";
    }
}
