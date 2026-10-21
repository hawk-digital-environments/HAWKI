<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\AiModelInfoEnrichmentPipeline;
use App\Services\Ai\ModelInformation\Events\ModelInfoEnrichedEvent;
use App\Services\Ai\ModelInformation\Events\ModelInfoFetchedEvent;
use App\Services\Ai\ModelInformation\Events\ModelInfoFetchStartingEvent;
use App\Services\Ai\ModelInformation\Events\SingleModelInfoEnrichedEvent;
use App\Services\Ai\ModelInformation\Events\SingleModelInfoFetchedEvent;
use App\Services\Ai\ModelInformation\Events\SingleModelInfoFetchStartingEvent;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Fetches and enriches AI model metadata for a given provider.
 *
 * This class is the orchestration layer for the model-sync process: it retrieves the
 * raw model list from a provider adapter and passes each model through the
 * {@see AiModelInfoEnrichmentPipeline} to augment it with additional information
 * (pricing, capabilities, flags, documentation URLs, etc.).
 *
 * Usage example:
 * ```php
 * // Fetch and enrich all models for a provider
 * $models = $modelInfoFetcher->fetchAll($providerProxy);
 *
 * // Fetch and enrich a single model by its ID
 * $model = $modelInfoFetcher->fetchSingle($providerProxy, 'gpt-4o');
 * ```
 *
 * The optional {@see JobMetrics} reference parameter carries structured log output
 * across multi-step operations. When omitted, one is created automatically.
 *
 * Successfully fetched provider model lists are memoized per provider for the lifetime
 * of this instance, so repeated {@see self::fetchSingle()} calls (e.g. one per configured
 * model during a config sync) trigger only a single adapter `getModels()` request per
 * provider. Failed fetches are never memoized and keep retrying on every call.
 */
class ModelInfoFetcher
{
    /** @var array<string, Collection<int, AiModel>> Provider model lists already fetched successfully, by provider. */
    private array $memoizedProviderModelLists = [];

    public function __construct(
        private readonly LoggerInterface               $logger,
        private readonly AiModelInfoEnrichmentPipeline $enrichmentPipeline
    )
    {
    }

    /**
     * Fetches all available models from a provider and enriches them.
     *
     * Returns an empty collection when the provider adapter fails to return a model list.
     *
     * @return Collection<int, AiModel>
     */
    public function fetchAll(AiProviderProxy $provider, JobMetrics|null &$metrics = null): Collection
    {
        $metrics = $metrics ?? new JobMetrics(
            'Model Info Fetcher | Provider: ' . $provider->name . ' (' . $provider->adapter_key . ')',
            $this->logger
        );

        $metrics->announceStart();

        ModelInfoFetchStartingEvent::dispatch($provider, $metrics);

        $models = $this->fetchProviderModelList($provider, $metrics);

        ModelInfoFetchedEvent::dispatch($provider, $models, $metrics);

        $enrichedModels = $this->enrichCollection($models ?? collect(), $provider, $metrics);

        ModelInfoEnrichedEvent::dispatch($provider, $enrichedModels, $metrics);

        $metrics->announceCompletion();

        return $enrichedModels;
    }

    /**
     * Fetches and enriches a single model by its `model_id` within a provider's catalog.
     *
     * Returns null when the model is not found in the provider's list or when the
     * enrichment pipeline returns an empty result for it.
     */
    public function fetchSingle(AiProviderProxy $provider, string $modelId, JobMetrics|null &$metrics = null): ?AiModel
    {
        $metrics = $metrics ?? new JobMetrics('Model Info Fetcher | Provider: ' . $provider->name . ' | Model: ' . $modelId, $this->logger);

        $metrics->announceStart();

        SingleModelInfoFetchStartingEvent::dispatch($provider, $modelId, $metrics);

        $models = $this->fetchProviderModelList($provider, $metrics);

        SingleModelInfoFetchedEvent::dispatch($provider, $models, $modelId, $metrics);

        $modelInfo = $models?->firstWhere(function (AiModel $model) use ($modelId) {
            return $model->model_id === $modelId
                || str_ends_with($model->model_id, "/$modelId")
                || str_ends_with($modelId, "/{$model->model_id}");
        });

        if (!$modelInfo) {
            $metrics->warning('Model ' . $modelId . ' not found in provider ' . $provider->name);
            return null;
        }

        $enrichedModelInfoCollection = $this->enrichCollection(collect([$modelInfo]), $provider, $metrics);
        if ($enrichedModelInfoCollection->isEmpty()) {
            $metrics->warning('Enrichment pipeline returned empty collection for model ' . $modelId . ' in provider ' . $provider->name);
            return null;
        }

        /** @var AiModel $enrichedModelInfo */
        $enrichedModelInfo = $enrichedModelInfoCollection->first();

        SingleModelInfoEnrichedEvent::dispatch($provider, $enrichedModelInfo, $modelId, $metrics);

        $metrics->announceCompletion();

        return $enrichedModelInfo;
    }

    /**
     * Retrieves the raw model list from the provider adapter.
     *
     * Returns null on adapter failure so callers can proceed with an empty collection
     * rather than aborting the entire sync run. Successful results are memoized per
     * provider ({@see self::getProviderListMemoKey()}); failures are not, so every
     * call retries the adapter.
     */
    private function fetchProviderModelList(AiProviderProxy $provider, JobMetrics $metrics): Collection|null
    {
        $memoKey = $this->getProviderListMemoKey($provider);
        if (isset($this->memoizedProviderModelLists[$memoKey])) {
            return $this->memoizedProviderModelLists[$memoKey];
        }

        try {
            $metrics->info('Fetching model list from provider ' . $provider->name);
            $models = $provider->adapter->getModels($provider);
        } catch (\Throwable $e) {
            $metrics->error('Error fetching model list from provider ' . $provider->name . ': ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }

        return $this->memoizedProviderModelLists[$memoKey] = $models;
    }

    /**
     * Builds the memo key identifying a provider's model list.
     *
     * Falls back to the underlying Eloquent model instance for providers that have not
     * been persisted yet (e.g. in tests), where no stable provider ID exists.
     */
    private function getProviderListMemoKey(AiProviderProxy $provider): string
    {
        $providerId = $provider->provider_id;
        if (!empty($providerId)) {
            return (string)$providerId;
        }

        return 'obj:' . spl_object_id($provider->getRealProvider());
    }

    /**
     * Passes each model through every enricher in the pipeline in sequence.
     *
     * Enricher failures are caught and logged; the model is left in its last
     * successfully enriched state and the pipeline continues.
     *
     * @param Collection<int, AiModel> $modelInfoCollection
     * @return Collection<int, AiModel>
     */
    private function enrichCollection(Collection $modelInfoCollection, AiProviderProxy $provider, JobMetrics $metrics): Collection
    {
        foreach ($this->enrichmentPipeline as $enricher) {
            try {
                $metrics->info('Enriching model info with ' . $enricher::class);

                $modelInfoCollection = $modelInfoCollection->map(function (AiModel $modelInfo) use ($enricher, $provider, $metrics) {
                    return $enricher->enrichModelInfo($modelInfo, $provider, $metrics);
                });

                $metrics->increment('Enrichment steps completed');
            } catch (\Throwable $e) {
                $metrics->error('Error during model info enrichment with ' . $enricher::class . ': ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        return $modelInfoCollection;
    }
}
