<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\StatusCheck;

use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusCheckFailedEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusCheckStartingEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusFetchedEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusFetchStartingEvent;
use App\Services\Ai\StatusCheck\Events\ModelProviderStatusUpdatedEvent;
use App\Services\Ai\StatusCheck\Events\ModelStatusCheckCompletedEvent;
use App\Services\Ai\StatusCheck\Events\ModelStatusCheckStartingEvent;
use App\Services\Ai\StatusCheck\ModelStatusUpdater;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(ModelStatusUpdater::class)]
class ModelStatusUpdaterTest extends TestCase
{
    private AiProviderRepository&MockObject $providerRepository;
    private AiModelRepository&MockObject $modelRepository;
    private AiProviderProxyResolver&MockObject $providerProxyResolver;
    private LoggerInterface&MockObject $logger;
    private ModelStatusUpdater $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->providerRepository = $this->createMock(AiProviderRepository::class);
        $this->providerRepository->method('makeScopeOverrides')->willReturn(new ScopeOverrides());
        $this->modelRepository = $this->createMock(AiModelRepository::class);
        $this->providerProxyResolver = $this->createMock(AiProviderProxyResolver::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new ModelStatusUpdater(
            $this->providerRepository,
            $this->modelRepository,
            $this->providerProxyResolver,
            $this->logger,
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(string $name = 'OpenAI'): AiProvider
    {
        $provider = new AiProvider();
        $provider->provider_id = 'openAi';
        $provider->name = $name;
        $provider->setRelation('models', new AiModelCollection());
        return $provider;
    }

    private function makeProviderWithModels(AiModel ...$models): AiProvider
    {
        $provider = $this->makeProvider();
        $provider->setRelation('models', new AiModelCollection($models));
        return $provider;
    }

    private function makeModel(string $modelId): AiModel
    {
        $model = new AiModel();
        $model->model_id = $modelId;
        return $model;
    }

    private function makeProxy(AiProvider $provider, ?ProviderAdapterInterface $adapter = null): AiProviderProxy
    {
        $adapter ??= $this->createMock(ProviderAdapterInterface::class);
        $driver = $this->createMock(Driver::class);
        return new AiProviderProxy($provider, $adapter, $driver);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(ModelStatusUpdater::class, $this->sut);
    }

    // =========================================================================
    // Repository failure
    // =========================================================================

    public function testItReturnsMetricsWithErrorWhenProviderRepositoryThrows(): void
    {
        $this->providerRepository
            ->method('findAllActive')
            ->willThrowException(new \RuntimeException('DB down'));

        $metrics = $this->sut->run();

        static::assertTrue($metrics->hasErrors());
    }

    public function testItReturnsWithoutDispatchingEventsWhenProviderRepositoryThrows(): void
    {
        Event::fake();
        $this->providerRepository
            ->method('findAllActive')
            ->willThrowException(new \RuntimeException('DB down'));

        $this->sut->run();

        Event::assertNothingDispatched();
    }

    public function testItReturnsZeroCountersWhenProviderRepositoryThrows(): void
    {
        $this->providerRepository
            ->method('findAllActive')
            ->willThrowException(new \RuntimeException('DB down'));

        $metrics = $this->sut->run();

        static::assertSame(0, $metrics->get(ModelStatusUpdater::METRIC_MODEL_COUNT));
        static::assertSame(0, $metrics->get(ModelStatusUpdater::METRIC_MODEL_ONLINE));
        static::assertSame(0, $metrics->get(ModelStatusUpdater::METRIC_MODEL_OFFLINE));
    }

    // =========================================================================
    // Successful run — no providers
    // =========================================================================

    public function testItDispatchesStartingAndCompletedEventsEvenWithNoProviders(): void
    {
        Event::fake();
        $this->providerRepository->method('findAllActive')->willReturn(new Collection());

        $this->sut->run();

        Event::assertDispatched(ModelStatusCheckStartingEvent::class);
        Event::assertDispatched(ModelStatusCheckCompletedEvent::class);
    }

    public function testItReturnsMetricsInstanceWithNoErrors(): void
    {
        $this->providerRepository->method('findAllActive')->willReturn(new Collection());

        $metrics = $this->sut->run();

        static::assertFalse($metrics->hasErrors());
    }

    // =========================================================================
    // Successful run — one provider, one model
    // =========================================================================

    public function testItIncrementsOnlineCounterWhenAdapterSetsModelOnline(): void
    {
        $model = $this->makeModel('gpt-4');
        $provider = $this->makeProviderWithModels($model);

        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('checkModelStatus')->willReturnCallback(
            function ($statusCollection) {
                $statusCollection->setOnline('gpt-4');
            }
        );

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willReturn($this->makeProxy($provider, $adapter));

        $metrics = $this->sut->run();

        static::assertSame(1, $metrics->get(ModelStatusUpdater::METRIC_MODEL_ONLINE));
        static::assertSame(0, $metrics->get(ModelStatusUpdater::METRIC_MODEL_OFFLINE));
        static::assertSame(1, $metrics->get(ModelStatusUpdater::METRIC_MODEL_COUNT));
    }

    public function testItIncrementsOfflineCounterWhenModelRemainsUnknownAfterCheck(): void
    {
        // A model still UNKNOWN after the adapter check is treated as offline
        $model = $this->makeModel('gpt-4');
        $provider = $this->makeProviderWithModels($model);

        $adapter = $this->createMock(ProviderAdapterInterface::class);
        // adapter does nothing — model stays UNKNOWN → should be set OFFLINE

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willReturn($this->makeProxy($provider, $adapter));

        $metrics = $this->sut->run();

        static::assertSame(0, $metrics->get(ModelStatusUpdater::METRIC_MODEL_ONLINE));
        static::assertSame(1, $metrics->get(ModelStatusUpdater::METRIC_MODEL_OFFLINE));
        static::assertSame(1, $metrics->get(ModelStatusUpdater::METRIC_MODEL_COUNT));
    }

    public function testItPersistsOnlineStatusViaModelRepository(): void
    {
        $model = $this->makeModel('gpt-4');
        $provider = $this->makeProviderWithModels($model);

        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('checkModelStatus')->willReturnCallback(
            function ($statusCollection) {
                $statusCollection->setOnline('gpt-4');
            }
        );

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willReturn($this->makeProxy($provider, $adapter));

        $this->modelRepository->expects(static::once())->method('setAiModelStatusTo');
        $this->modelRepository->expects(static::once())->method('setAiModelDemandTo');

        $this->sut->run();
    }

    // =========================================================================
    // Per-provider failure
    // =========================================================================

    public function testItContinuesProcessingRemainingProvidersAfterOneThrows(): void
    {
        $failingProvider = $this->makeProvider('Failing');
        $successProvider = $this->makeProvider('Success');
        $model = $this->makeModel('gpt-4');
        $successProvider->setRelation('models', new AiModelCollection([$model]));

        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('checkModelStatus')->willReturnCallback(
            function ($statusCollection) {
                $statusCollection->setOnline('gpt-4');
            }
        );

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$failingProvider, $successProvider]));

        $this->providerProxyResolver->method('resolve')
            ->willReturnCallback(function (AiProvider $p) use ($failingProvider, $successProvider, $adapter) {
                if ($p === $failingProvider) {
                    throw new \RuntimeException('Adapter init failed');
                }
                return $this->makeProxy($successProvider, $adapter);
            });

        $metrics = $this->sut->run();

        static::assertTrue($metrics->hasErrors());
        static::assertSame(1, $metrics->get(ModelStatusUpdater::METRIC_MODEL_ONLINE));
    }

    public function testItRecordsErrorWhenProviderCheckThrows(): void
    {
        $provider = $this->makeProvider();

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $metrics = $this->sut->run();

        static::assertTrue($metrics->hasErrors());
    }

    // =========================================================================
    // Event dispatching
    // =========================================================================

    public function testItDispatchesProviderStatusCheckStartingEventPerProvider(): void
    {
        Event::fake();
        $provider = $this->makeProvider();
        $adapter = $this->createMock(ProviderAdapterInterface::class);

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willReturn($this->makeProxy($provider, $adapter));

        $this->sut->run();

        Event::assertDispatched(ModelProviderStatusCheckStartingEvent::class);
    }

    public function testItDispatchesProviderStatusFetchStartingEvent(): void
    {
        Event::fake();
        $provider = $this->makeProvider();
        $adapter = $this->createMock(ProviderAdapterInterface::class);

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willReturn($this->makeProxy($provider, $adapter));

        $this->sut->run();

        Event::assertDispatched(ModelProviderStatusFetchStartingEvent::class);
    }

    public function testItDispatchesProviderStatusFetchedEvent(): void
    {
        Event::fake();
        $provider = $this->makeProvider();
        $adapter = $this->createMock(ProviderAdapterInterface::class);

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willReturn($this->makeProxy($provider, $adapter));

        $this->sut->run();

        Event::assertDispatched(ModelProviderStatusFetchedEvent::class);
    }

    public function testItDispatchesProviderStatusUpdatedEvent(): void
    {
        Event::fake();
        $provider = $this->makeProvider();
        $adapter = $this->createMock(ProviderAdapterInterface::class);

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willReturn($this->makeProxy($provider, $adapter));

        $this->sut->run();

        Event::assertDispatched(ModelProviderStatusUpdatedEvent::class);
    }

    public function testItDispatchesProviderStatusCheckFailedEventWhenProviderThrows(): void
    {
        Event::fake();
        $provider = $this->makeProvider();

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->sut->run();

        Event::assertDispatched(ModelProviderStatusCheckFailedEvent::class);
    }

    public function testItDoesNotDispatchProviderStatusUpdatedEventWhenProviderThrows(): void
    {
        Event::fake();
        $provider = $this->makeProvider();

        $this->providerRepository->method('findAllActive')
            ->willReturn(new Collection([$provider]));
        $this->providerProxyResolver->method('resolve')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->sut->run();

        Event::assertNotDispatched(ModelProviderStatusUpdatedEvent::class);
    }
}
