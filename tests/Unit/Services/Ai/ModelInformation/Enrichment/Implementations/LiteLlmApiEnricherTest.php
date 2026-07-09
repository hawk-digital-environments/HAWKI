<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation\Enrichment\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\ModelInformation\Enrichment\Events\LiteLlmEnrichmentCompletedEvent;
use App\Services\Ai\ModelInformation\Enrichment\Events\LiteLlmEnrichmentSkippedEvent;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmApiDataStore;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmModelData;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\StaticLiteLlmDataStore;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlmApiEnricher;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Utils\JobMetrics;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LiteLlmApiEnricher::class)]
class LiteLlmApiEnricherTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(
        LiteLlmApiDataStore    $apiDataStore,
        StaticLiteLlmDataStore $staticDataStore
    ): LiteLlmApiEnricher
    {
        return new LiteLlmApiEnricher($apiDataStore, $staticDataStore);
    }

    private function makeModel(string $modelId = 'gpt-4o', string|null $modelType = null): AiModel
    {
        return new AiModel([
            'model_id' => $modelId,
            'model_type' => $modelType,
        ]);
    }

    private function makeLiteLlmModelData(array $data): LiteLlmModelData
    {
        return new LiteLlmModelData(
            modelId: $data['id'] ?? 'gpt-4o',
            otherModelIds: [],
            data: $data
        );
    }

    private function makeJobMetrics(): JobMetrics
    {
        return new JobMetrics('test');
    }

    private function makeProvider(): AiProviderProxy
    {
        return new AiProviderProxy(
            provider: new AiProvider(),
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->createMock(Driver::class),
        );
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut(
            $this->createMock(LiteLlmApiDataStore::class),
            $this->createMock(StaticLiteLlmDataStore::class)
        );
        static::assertInstanceOf(LiteLlmApiEnricher::class, $sut);
    }

    // =========================================================================
    // Short-circuit: empty model_id
    // =========================================================================

    public function testItReturnsModelUnchangedWhenModelIdIsEmpty(): void
    {
        Event::fake();

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->expects(static::never())->method('getModelInformation');

        $staticStore = $this->createMock(StaticLiteLlmDataStore::class);
        $staticStore->expects(static::never())->method('getModelInformation');

        $sut = $this->makeSut($apiStore, $staticStore);
        $model = $this->makeModel('');
        $result = $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertSame($model, $result);

        Event::assertDispatchedTimes('eloquent.booting: ' . AiModel::class, 1);
        Event::assertDispatchedTimes('eloquent.booting: ' . AiProvider::class, 1);
    }

    // =========================================================================
    // No LiteLLM data found → skipped event
    // =========================================================================

    public function testItDispatchesSkippedEventWhenApiReturnsNullAndStaticReturnsNull(): void
    {
        Event::fake();

        $provider = $this->makeProvider();

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn(null);

        $staticStore = $this->createMock(StaticLiteLlmDataStore::class);
        $staticStore->expects(static::never())->method('getModelInformation');

        $sut = $this->makeSut($apiStore, $staticStore);
        $model = $this->makeModel();
        $result = $sut->enrichModelInfo($model, $provider, $this->makeJobMetrics());

        static::assertSame($model, $result);
        Event::assertDispatched(LiteLlmEnrichmentSkippedEvent::class);
        Event::assertNotDispatched(LiteLlmEnrichmentCompletedEvent::class);
    }

    public function testItReturnsModelUnchangedWhenNoDataFound(): void
    {
        Event::fake();

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn(null);

        $staticStore = $this->createMock(StaticLiteLlmDataStore::class);

        $sut = $this->makeSut($apiStore, $staticStore);
        $model = $this->makeModel('gpt-4o', WellKnownModelTypes::CHAT);
        $result = $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertSame($model, $result);
    }

    // =========================================================================
    // API failure → fallback to static store
    // =========================================================================

    public function testItFallsBackToStaticStoreWhenApiThrows(): void
    {
        Event::fake();

        $provider = $this->makeProvider();
        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'gpt-4o', 'mode' => 'chat']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willThrowException(new \RuntimeException('API timeout'));

        $staticStore = $this->createMock(StaticLiteLlmDataStore::class);
        $staticStore->expects(static::once())
            ->method('getModelInformation')
            ->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $staticStore);
        $model = $this->makeModel('gpt-4o', WellKnownModelTypes::CHAT);
        $result = $sut->enrichModelInfo($model, $provider, $this->makeJobMetrics());

        // Enrichment ran (completed event, not skipped)
        Event::assertDispatched(LiteLlmEnrichmentCompletedEvent::class);
        Event::assertNotDispatched(LiteLlmEnrichmentSkippedEvent::class);
        static::assertInstanceOf(AiModel::class, $result);
    }

    public function testItDispatchesSkippedEventWhenApiThrowsAndStaticReturnsNull(): void
    {
        Event::fake();

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willThrowException(new \RuntimeException('offline'));

        $staticStore = $this->createMock(StaticLiteLlmDataStore::class);
        $staticStore->method('getModelInformation')->willReturn(null);

        $sut = $this->makeSut($apiStore, $staticStore);
        $model = $this->makeModel();
        $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        Event::assertDispatched(LiteLlmEnrichmentSkippedEvent::class);
    }

    // =========================================================================
    // Model type inference
    // =========================================================================

    public function testItInfersModelTypeFromChatMode(): void
    {
        Event::fake();

        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'gpt-4o', 'mode' => 'chat']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel('gpt-4o', null);
        $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertSame(WellKnownModelTypes::CHAT, $model->model_type);
    }

    public function testItInfersModelTypeFromCompletionsMode(): void
    {
        Event::fake();

        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'gpt-4o', 'mode' => 'completions']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel('gpt-4o', null);
        $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertSame(WellKnownModelTypes::CHAT, $model->model_type);
    }

    public function testItInfersModelTypeFromResponseMode(): void
    {
        Event::fake();

        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'gpt-4o', 'mode' => 'response']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel('gpt-4o', null);
        $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertSame(WellKnownModelTypes::CHAT, $model->model_type);
    }

    public function testItInfersModelTypeFromImageGenerationMode(): void
    {
        Event::fake();

        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'dall-e-3', 'mode' => 'image_generation']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel('dall-e-3', null);
        $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertSame(WellKnownModelTypes::IMAGE_GENERATION, $model->model_type);
    }

    public function testItInfersModelTypeFromVideoGenerationMode(): void
    {
        Event::fake();

        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'sora', 'mode' => 'video_generation']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel('sora', null);
        $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertSame(WellKnownModelTypes::VIDEO_GENERATION, $model->model_type);
    }

    public function testItLeavesModelTypeNullWhenModeIsUnrecognised(): void
    {
        Event::fake();

        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'embed-model', 'mode' => 'embedding']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel('embed-model', null);
        $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertNull($model->model_type);
    }

    public function testItDoesNotOverwriteExistingModelType(): void
    {
        Event::fake();

        // model_type is already set to CHAT; LiteLLM says image_generation
        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'gpt-4o', 'mode' => 'image_generation']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel('gpt-4o', WellKnownModelTypes::CHAT);
        $sut->enrichModelInfo($model, $this->makeProvider(), $this->makeJobMetrics());

        static::assertSame(WellKnownModelTypes::CHAT, $model->model_type);
    }

    // =========================================================================
    // Completed event payload
    // =========================================================================

    public function testItDispatchesCompletedEventWithCorrectPayload(): void
    {
        Event::fake();

        $provider = $this->makeProvider();
        $jobMetrics = $this->makeJobMetrics();
        $liteLlmData = $this->makeLiteLlmModelData(['id' => 'gpt-4o', 'mode' => 'chat']);

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn($liteLlmData);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel('gpt-4o', WellKnownModelTypes::CHAT);
        $sut->enrichModelInfo($model, $provider, $jobMetrics);

        Event::assertDispatched(
            LiteLlmEnrichmentCompletedEvent::class,
            function (LiteLlmEnrichmentCompletedEvent $event) use ($provider, $jobMetrics, $liteLlmData) {
                return $event->provider === $provider
                    && $event->jobMetrics === $jobMetrics
                    && $event->liteLlmData === $liteLlmData;
            }
        );
    }

    public function testItDispatchesSkippedEventWithCorrectEnricher(): void
    {
        Event::fake();

        $provider = $this->makeProvider();
        $jobMetrics = $this->makeJobMetrics();

        $apiStore = $this->createMock(LiteLlmApiDataStore::class);
        $apiStore->method('getModelInformation')->willReturn(null);

        $sut = $this->makeSut($apiStore, $this->createMock(StaticLiteLlmDataStore::class));
        $model = $this->makeModel();
        $sut->enrichModelInfo($model, $provider, $jobMetrics);

        Event::assertDispatched(
            LiteLlmEnrichmentSkippedEvent::class,
            function (LiteLlmEnrichmentSkippedEvent $event) use ($sut, $provider, $jobMetrics) {
                return $event->enricher === $sut
                    && $event->provider === $provider
                    && $event->jobMetrics === $jobMetrics;
            }
        );
    }
}
