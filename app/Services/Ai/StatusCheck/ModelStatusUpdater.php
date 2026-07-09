<?php
declare(strict_types=1);


namespace App\Services\Ai\StatusCheck;


use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusCheckFailedEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusCheckStartingEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusFetchedEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusFetchStartingEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusUpdatedEvent;
use App\Services\Ai\StatusCheck\Events\ModelStatusCheckCompletedEvent;
use App\Services\Ai\StatusCheck\Events\ModelStatusCheckStartingEvent;
use App\Services\Ai\Values\OnlineStatus;
use App\Utils\JobMetrics;
use Psr\Log\LoggerInterface;

/**
 * Background job that iterates every active AI provider, asks its adapter to check the online
 * status and demand level of all associated models, and then persists the results.
 *
 * Designed to be called from a scheduled Artisan command.  Each provider is processed
 * independently so that a failure in one does not abort the remaining providers — the error is
 * captured in the returned {@see JobMetrics} and the loop continues.
 *
 * The update flow per provider is:
 * 1. Resolve the provider proxy (adapter + driver).
 * 2. Build fresh {@see AiModelOnlineStatusCollection} and {@see AiModelDemandCollection} instances.
 * 3. Fire {@see ModelProviderStatusFetchStartingEvent} so listeners can pre-populate statuses.
 * 4. Call {@see ProviderAdapterInterface::checkModelStatus()} to let the adapter fill in results.
 * 5. Fire {@see ModelProviderStatusFetchedEvent} so listeners can post-process results.
 * 6. Mark any model still in UNKNOWN status as OFFLINE (it was absent from the provider response).
 * 7. Persist online statuses and demand levels via the model repository.
 * 8. Fire {@see ModelProviderStatusUpdatedEvent}.
 *
 * All lifecycle stages are covered by domain events, allowing external code (e.g. notification
 * listeners) to react without coupling to this class.
 */
readonly class ModelStatusUpdater
{
    public const string METRIC_MODEL_ONLINE = 'Models ONLINE';
    public const string METRIC_MODEL_OFFLINE = 'Models OFFLINE';
    public const string METRIC_MODEL_COUNT = 'Models checked';

    public function __construct(
        private AiProviderRepository    $providerRepository,
        private AiModelRepository       $modelRepository,
        private AiProviderProxyResolver $providerProxyResolver,
        private LoggerInterface         $logger
    )
    {
    }

    /**
     * Executes the status-check run across all active providers and returns aggregated metrics.
     *
     * If fetching the provider list from the database fails entirely, an error is recorded in the
     * metrics and an empty result is returned immediately (no events are dispatched).  Per-provider
     * failures are caught individually, recorded as errors, and the run continues with the next
     * provider.
     */
    public function run(): JobMetrics
    {
        $metrics = new JobMetrics('AI Model Status Update', $this->logger);

        $metrics->announceStart();

        try {
            $providers = $this->providerRepository->findAllActive($this->providerRepository->makeScopeOverrides());
        } catch (\Throwable $e) {
            $metrics->error('Failed to retrieve active AI providers for model status update: ' . $e->getMessage(), ['exception' => $e]);
            return $metrics;
        }

        ModelStatusCheckStartingEvent::dispatch($metrics);

        foreach ($providers as $provider) {
            try {
                $metrics->debug(sprintf('Checking model status for provider %s', $provider->name));

                ModelProviderStatusCheckStartingEvent::dispatch($provider);

                $providerProxy = $this->providerProxyResolver->resolve($provider);

                $models = $provider->models;
                $statusCollection = new AiModelOnlineStatusCollection($models, $this->logger);
                $demandCollection = new AiModelDemandCollection($models, $this->logger);

                ModelProviderStatusFetchStartingEvent::dispatch($statusCollection, $demandCollection, $provider);

                $providerProxy->adapter->checkModelStatus(
                    statusCollection: $statusCollection,
                    demandCollection: $demandCollection,
                    provider: $providerProxy
                );

                ModelProviderStatusFetchedEvent::dispatch($statusCollection, $demandCollection, $provider);

                // At this point we checked all models of the provider that we know about, and marked the ones that are online.
                // If there are some still "unknown", it means they were not present in the provider's status response, so we can safely mark them as offline.
                $statusCollection->setAllUnknownToOffline();

                foreach ($statusCollection->getChangedList() as $model_id => $modelStatus) {
                    $metrics->info(sprintf(
                        'Model %s (%s) is %s',
                        $model_id,
                        $provider->name,
                        strtoupper($modelStatus->value)
                    ));
                    $metrics->increment($modelStatus === OnlineStatus::ONLINE ? self::METRIC_MODEL_ONLINE : self::METRIC_MODEL_OFFLINE);
                    $metrics->increment(self::METRIC_MODEL_COUNT);
                }

                $this->modelRepository->setAiModelStatusTo($statusCollection);
                $this->modelRepository->setAiModelDemandTo($demandCollection);

                ModelProviderStatusUpdatedEvent::dispatch($provider);

            } catch (\Throwable $e) {
                $metrics->error(sprintf(
                    'Failed to update model status for provider %s: %s',
                    $provider->name,
                    $e->getMessage()
                ), ['exception' => $e]);

                ModelProviderStatusCheckFailedEvent::dispatch($provider, $e, $metrics);
            }
        }

        ModelStatusCheckCompletedEvent::dispatch($metrics);

        $metrics->announceCompletion();

        return $metrics;
    }
}
