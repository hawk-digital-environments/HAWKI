<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\ModelInformation\Enrichment\AiModelInfoEnrichmentPipeline;
use App\Services\Ai\ModelInformation\ModelInfoFetcher;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Illuminate\Support\Collection;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(ModelInfoFetcher::class)]
class ModelInfoFetcherTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function makeSut(): ModelInfoFetcher
    {
        // A real, empty pipeline: no enrichers to run, models pass through unchanged
        return new ModelInfoFetcher($this->logger, new AiModelInfoEnrichmentPipeline());
    }

    /**
     * Builds a proxy for the given provider row. Each call returns a fresh proxy (like
     * the config syncer resolving a new proxy per model), so tests prove memoization
     * survives new proxy instances for the same provider.
     */
    private function makeProvider(AiProvider $row, ProviderAdapterInterface $adapter): AiProviderProxy
    {
        $driver = $this->createMock(Driver::class);
        $driver->method('driver')->willReturn('zai');

        return new AiProviderProxy(
            provider: $row,
            adapter: $adapter,
            driver: $driver,
        );
    }

    private function makeProviderRow(): AiProvider
    {
        $row = new AiProvider();
        $row->provider_id = 'zai';
        $row->name = 'Zai';
        $row->adapter_key = 'zai';

        return $row;
    }

    private function makeAdapter(Collection $models): ProviderAdapterInterface&MockObject
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('getModels')->willReturn($models);

        return $adapter;
    }

    private function zaiModels(): Collection
    {
        return collect([
            new AiModel(['model_id' => 'zai/glm-5']),
            new AiModel(['model_id' => 'zai/glm-4.7']),
        ]);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(ModelInfoFetcher::class, $this->makeSut());
    }

    // =========================================================================
    // Single model fetch
    // =========================================================================

    public function testItFetchesASingleModelByExactId(): void
    {
        $provider = $this->makeProvider($this->makeProviderRow(), $this->makeAdapter($this->zaiModels()));

        $model = $this->makeSut()->fetchSingle($provider, 'zai/glm-4.7');

        static::assertSame('zai/glm-4.7', $model?->model_id);
    }

    public function testItFetchesASingleModelBySuffixMatch(): void
    {
        $provider = $this->makeProvider($this->makeProviderRow(), $this->makeAdapter($this->zaiModels()));

        $model = $this->makeSut()->fetchSingle($provider, 'glm-5');

        static::assertSame('zai/glm-5', $model?->model_id);
    }

    public function testItReturnsNullForAModelNotOfferedByTheProvider(): void
    {
        $provider = $this->makeProvider($this->makeProviderRow(), $this->makeAdapter($this->zaiModels()));

        $model = $this->makeSut()->fetchSingle($provider, 'gpt-4o');

        static::assertNull($model);
    }

    // =========================================================================
    // Provider model list memoization
    // =========================================================================

    public function testItFetchesTheProviderModelListOnlyOnceForRepeatedSingleFetches(): void
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->expects(static::exactly(1))
            ->method('getModels')
            ->willReturn($this->zaiModels());

        $row = $this->makeProviderRow();
        $sut = $this->makeSut();

        // Fresh proxies per call, like the config syncer resolves one per model
        $sut->fetchSingle($this->makeProvider($row, $adapter), 'zai/glm-5');
        $sut->fetchSingle($this->makeProvider($row, $adapter), 'zai/glm-4.7');
        $sut->fetchSingle($this->makeProvider($row, $adapter), 'glm-5');
    }

    public function testItFetchesTheProviderModelListOnceForFetchAllPlusSingleFetches(): void
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->expects(static::exactly(1))
            ->method('getModels')
            ->willReturn($this->zaiModels());

        $row = $this->makeProviderRow();
        $sut = $this->makeSut();

        $sut->fetchAll($this->makeProvider($row, $adapter));
        $sut->fetchSingle($this->makeProvider($row, $adapter), 'zai/glm-5');
    }

    public function testItMemoizesModelListsPerProvider(): void
    {
        $zaiAdapter = $this->createMock(ProviderAdapterInterface::class);
        $zaiAdapter->expects(static::exactly(1))->method('getModels')->willReturn($this->zaiModels());

        $openAiRow = $this->makeProviderRow();
        $openAiRow->provider_id = 'openAi';
        $openAiRow->name = 'OpenAI';
        $openAiAdapter = $this->createMock(ProviderAdapterInterface::class);
        $openAiAdapter->expects(static::exactly(1))
            ->method('getModels')
            ->willReturn(collect([new AiModel(['model_id' => 'gpt-4o'])]));

        $sut = $this->makeSut();
        $sut->fetchSingle($this->makeProvider($this->makeProviderRow(), $zaiAdapter), 'zai/glm-5');
        $sut->fetchSingle($this->makeProvider($openAiRow, $openAiAdapter), 'gpt-4o');
    }

    public function testItDoesNotMemoizeFailedModelListFetches(): void
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->expects(static::exactly(2))
            ->method('getModels')
            ->willReturnCallback(static fn(): Collection => throw new \RuntimeException('provider down'));

        $row = $this->makeProviderRow();
        $sut = $this->makeSut();

        // Both calls fail (returning null), but the adapter is consulted again the second
        // time: failures are not memoized, so a recovering provider is picked up
        static::assertNull($sut->fetchSingle($this->makeProvider($row, $adapter), 'zai/glm-5'));
        static::assertNull($sut->fetchSingle($this->makeProvider($row, $adapter), 'zai/glm-5'));
    }

    // =========================================================================
    // Fetch all
    // =========================================================================

    public function testItFetchesAndReturnsAllModelsOfAProvider(): void
    {
        $provider = $this->makeProvider($this->makeProviderRow(), $this->makeAdapter($this->zaiModels()));

        $models = $this->makeSut()->fetchAll($provider);

        static::assertSame(['zai/glm-5', 'zai/glm-4.7'], $models->pluck('model_id')->all());
    }

    public function testItReturnsAnEmptyCollectionWhenTheAdapterFails(): void
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('getModels')->willReturnCallback(static fn(): Collection => throw new \RuntimeException('provider down'));

        $provider = $this->makeProvider($this->makeProviderRow(), $adapter);

        $models = $this->makeSut()->fetchAll($provider);

        static::assertTrue($models->isEmpty());
    }
}
