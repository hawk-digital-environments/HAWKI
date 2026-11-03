<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;


use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\System\Time\CarbonClockInterface;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;

/**
 * Fetches LiteLLM model data from the live public API.
 *
 * The full catalog is downloaded once and cached under a single shared key, regardless of
 * how many providers are enriched. The requested provider's records are filtered out of
 * the cached catalog at read time.
 *
 * Freshness is maintained with conditional requests: every {@see self::REVALIDATION_TTL},
 * the stored ETag is submitted via If-None-Match, which upstream answers with a tiny 304
 * response as long as the catalog did not change. While the upstream is unreachable, the
 * cached catalog keeps being served for up to {@see self::DATA_TTL} after the last
 * successful confirmation; beyond that, lookups throw so the caller can fall back to the
 * static data files.
 *
 * @see StaticLiteLlmDataStore for the offline fallback used when the API is unavailable.
 */
class LiteLlmApiDataStore extends AbstractLiteLlmDataStore
{
    private const string CATALOG_CACHE_KEY = 'lite_llm_model_catalog';

    /** How often the cached catalog is revalidated against the upstream (conditional GET). */
    private const int REVALIDATION_TTL_SECONDS = 5 * 60;

    /** How long the cached catalog may be served without a successful upstream confirmation. */
    private const int DATA_TTL_SECONDS = 24 * 60 * 60;

    public function __construct(
        private readonly LiteLlmApiClient     $apiClient,
        private readonly Repository           $cache,
        LoggerInterface                       $logger,
        LiteLlmDriverNameProviderNameMapping  $nameMapping,
        private readonly CarbonClockInterface $clock
    )
    {
        parent::__construct($logger, $nameMapping);
    }

    /** Evicts the cached catalog, forcing a fresh download on the next access. */
    public function clearModelInformationCache(): void
    {
        $this->cache->forget(self::CATALOG_CACHE_KEY);
        $this->clearMemoizedProviderLists();
    }

    /**
     * Loads the provider's model data from the shared cached catalog (see class docblock).
     *
     * @return iterable<LiteLlmModelData>
     */
    protected function getProviderModelInformationList(AiProviderProxy $provider): iterable
    {
        $providerName = $this->nameMapping->getProviderNameFromProxy($provider);
        $catalog = $this->getConfirmedCatalog();

        return $this->loadModelDataList(
            $provider,
            $this->apiClient->normalizeCatalog($catalog, $providerName)
        );
    }

    /**
     * @return array{builtAt: int}
     */
    protected function createMemoAnchor(): array
    {
        return ['builtAt' => $this->clock->now()->getTimestamp()];
    }

    /**
     * Memoized lists expire with the revalidation window: within it, lookups would have
     * served the Redis-cached catalog without upstream contact anyway, so serving the
     * in-memory list is equivalent. During upstream outages this also keeps the memo
     * damping retries instead of dying immediately.
     */
    protected function isMemoizedProviderListValid(array $anchor): bool
    {
        return $this->clock->now()->getTimestamp() - $anchor['builtAt'] < self::REVALIDATION_TTL_SECONDS;
    }

    /**
     * Returns the cached catalog, revalidating it against the upstream when the last
     * confirmation is older than {@see self::REVALIDATION_TTL_SECONDS}.
     *
     * Revalidation is always a conditional GET via the stored ETag: an unchanged catalog
     * is confirmed with a tiny 304 response, a changed one arrives as a fresh download.
     *
     * A failed revalidation is not fatal: the cached catalog keeps being served as long as
     * it lies within {@see self::DATA_TTL_SECONDS} after the last successful confirmation,
     * so short upstream outages go unnoticed. Once the data is older than that, the
     * failure propagates so the caller can fall back to the static data files.
     *
     * @return array<string, mixed> The confirmed catalog, keyed by model ID.
     */
    private function getConfirmedCatalog(): array
    {
        /**
         * @var array{etag: string|null, catalog: array<string, mixed>, revalidatedAt: int}|null $entry
         */
        $entry = $this->cache->get(self::CATALOG_CACHE_KEY);
        $now = $this->clock->now();

        if ($entry === null) {
            return $this->storeCatalog($this->apiClient->fetchRawCatalog(), $now);
        }

        $confirmedAge = $now->getTimestamp() - $entry['revalidatedAt'];
        if ($confirmedAge < self::REVALIDATION_TTL_SECONDS) {
            return $entry['catalog'];
        }

        try {
            $fetch = $this->apiClient->fetchRawCatalog($entry['etag']);
        } catch (\Throwable $e) {
            if ($confirmedAge > self::DATA_TTL_SECONDS) {
                // Too old to be served without confirmation: propagate the failure so the
                // caller can fall back to the static data files
                throw $e;
            }

            $this->logger->warning('Could not revalidate the cached LiteLLM catalog, serving cached data', [
                'exception' => $e,
            ]);
            return $entry['catalog'];
        }

        if ($fetch->notModified) {
            // The catalog is confirmed unchanged: refresh the confirmation timestamp and TTL
            $entry['revalidatedAt'] = $now->getTimestamp();
            $this->cache->put(self::CATALOG_CACHE_KEY, $entry, $now->addSeconds(self::DATA_TTL_SECONDS));

            return $entry['catalog'];
        }

        return $this->storeCatalog($fetch, $now);
    }

    /**
     * Stores a downloaded catalog under the shared cache key and returns it.
     *
     * @return array<string, mixed>
     */
    private function storeCatalog(LiteLlmCatalogFetch $fetch, \Carbon\CarbonImmutable $now): array
    {
        $this->cache->put(self::CATALOG_CACHE_KEY, [
            'etag'         => $fetch->etag,
            'catalog'      => $fetch->catalog,
            'revalidatedAt' => $now->getTimestamp(),
        ], $now->addSeconds(self::DATA_TTL_SECONDS));

        return $fetch->catalog;
    }
}
