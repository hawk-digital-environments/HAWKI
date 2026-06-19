<?php
declare(strict_types=1);


namespace App\Services\Ai\StatusCheck;


use App\Services\Ai\Registries\ProviderAdapterRegistry;
use App\Services\Ai\Repositories\AiModelRepository;
use App\Services\Ai\Repositories\AiProviderRepository;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusCheckFailedEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusCheckStartingEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusFetchedEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusFetchStartingEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusUpdatedEvent;
use App\Services\Ai\StatusCheck\Events\ModelStatusCheckCompletedEvent;
use App\Services\Ai\StatusCheck\Events\ModelStatusCheckStartingEvent;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\Ai\Values\ParameterSource;
use App\Utils\JobMetrics;
use Psr\Log\LoggerInterface;

readonly class ModelStatusUpdater
{
    public const string METRIC_MODEL_ONLINE = 'Models ONLINE';
    public const string METRIC_MODEL_OFFLINE = 'Models OFFLINE';
    public const string METRIC_MODEL_COUNT = 'Models checked';

    public function __construct(
        private AiProviderRepository    $providerRepository,
        private AiModelRepository       $modelRepository,
        private ProviderAdapterRegistry $adapterRegistry,
        private LoggerInterface         $logger
    )
    {
    }

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

                $adapter = $this->adapterRegistry->getForProvider($provider);

                $models = $provider->models;
                $statusCollection = new AiModelOnlineStatusCollection($models, $this->logger);
                $demandCollection = new AiModelDemandCollection($models, $this->logger);

                ModelProviderStatusFetchStartingEvent::dispatch($statusCollection, $demandCollection, $provider);

                $adapter->checkModelStatus(
                    statusCollection: $statusCollection,
                    demandCollection: $demandCollection,
                    source: ParameterSource::fromProvider($provider)
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
