<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;

use App\Models\Ai\AiProvider;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmDriverNameProviderNameMapping;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\StaticLiteLlmDataStore;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\System\Container\SystemPaths;
use Illuminate\Foundation\Application;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(StaticLiteLlmDataStore::class)]
class StaticLiteLlmDataStoreTest extends TestCase
{
    private string $storageDirectory;
    private LoggerInterface&MockObject $logger;

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageDirectory = sys_get_temp_dir() . '/static_lite_llm_data_store_test_' . uniqid();
        mkdir($this->storageDirectory . '/static_llm_data/lite_llm', recursive: true);

        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storageDirectory . '/static_llm_data/lite_llm/*.php') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->storageDirectory . '/static_llm_data/lite_llm');
        @rmdir($this->storageDirectory . '/static_llm_data');
        @rmdir($this->storageDirectory);

        parent::tearDown();
    }

    private function makeSut(): StaticLiteLlmDataStore
    {
        // SystemPaths is readonly and cannot be mocked: hand it an application stub
        // whose resourcePath() points at this test's temporary directory
        $application = $this->createMock(Application::class);
        $application->method('resourcePath')->willReturnCallback(
            fn(string $path = ''): string => $path === '' ? $this->storageDirectory : $this->storageDirectory . '/' . $path
        );

        return new StaticLiteLlmDataStore(
            $this->logger,
            new LiteLlmDriverNameProviderNameMapping(),
            new SystemPaths($application),
        );
    }

    private function makeProvider(string $driverName): AiProviderProxy
    {
        $driver = $this->createMock(Driver::class);
        $driver->method('driver')->willReturn($driverName);

        return new AiProviderProxy(
            provider: new AiProvider(),
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $driver,
        );
    }

    /** Writes a static provider data file as {@see \App\Console\Commands\Dev\UpdateLiteLlmStaticDataCommand} would. */
    private function writeProviderFile(string $providerName, array $data): void
    {
        $content = '<?php return ' . var_export($data, true) . ';';
        file_put_contents($this->makeSut()->getProviderFilePath($providerName), $content);
    }

    private function zaiStaticData(): array
    {
        return [
            ['id' => 'zai/glm-5', 'provider' => 'zai', 'max_input_tokens' => 200000],
            ['id' => 'glm-4.7', 'provider' => 'zai', 'max_input_tokens' => 128000],
        ];
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(StaticLiteLlmDataStore::class, $this->makeSut());
    }

    // =========================================================================
    // Reading the static files
    // =========================================================================

    public function testItReadsModelDataFromTheStaticFile(): void
    {
        $this->writeProviderFile('zai', $this->zaiStaticData());

        $model = $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        static::assertSame('zai/glm-5', $model?->modelId);
        static::assertSame(200000, $model?->get('max_input_tokens'));
    }

    public function testItMatchesModelIdsBySuffix(): void
    {
        $this->writeProviderFile('zai', $this->zaiStaticData());

        $model = $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'zai/glm-4.7');

        static::assertSame('glm-4.7', $model?->modelId);
    }

    public function testItReturnsNullWhenNoModelMatches(): void
    {
        $this->writeProviderFile('zai', $this->zaiStaticData());

        $model = $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'gpt-4o');

        static::assertNull($model);
    }

    public function testItSkipsRecordsOfOtherProvidersWithAnErrorLog(): void
    {
        $this->writeProviderFile('zai', [
            ['id' => 'zai/glm-5', 'provider' => 'zai'],
            ['id' => 'gpt-4o', 'provider' => 'openai'],
        ]);

        $this->logger
            ->expects(static::once())
            ->method('error')
            ->with('Model data has an unexpected "provider" field', static::callback(
                fn(array $context): bool => ($context['model_provider'] ?? null) === 'openai'
            ));

        static::assertSame('zai/glm-5', $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'zai/glm-5')?->modelId);
    }

    public function testItReturnsNullWhenTheProviderFileIsMissing(): void
    {
        $this->logger
            ->expects(static::once())
            ->method('warning')
            ->with('Static Lite LLM data file not found for provider', static::callback(
                fn(array $context): bool => isset($context['expected_file'])
            ));

        $model = $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        static::assertNull($model);
    }

    public function testItReturnsNullWhenTheProviderFileIsInvalid(): void
    {
        file_put_contents($this->makeSut()->getProviderFilePath('zai'), '<?php return "not an array";');

        $this->logger
            ->expects(static::once())
            ->method('error')
            ->with('Static Lite LLM data is not an array', static::callback(
                fn(array $context): bool => ($context['actual_type'] ?? null) === 'string'
            ));

        $model = $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        static::assertNull($model);
    }

    // =========================================================================
    // In-memory memoization
    // =========================================================================

    public function testItMemoizesTheProviderListForTheInstanceLifetime(): void
    {
        $this->writeProviderFile('zai', $this->zaiStaticData());

        $sut = $this->makeSut();
        static::assertSame(200000, $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5')?->get('max_input_tokens'));

        // The file changes on disk, but this instance keeps serving its memoized list
        $this->writeProviderFile('zai', [
            ['id' => 'zai/glm-5', 'provider' => 'zai', 'max_input_tokens' => 999],
        ]);
        static::assertSame(200000, $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5')?->get('max_input_tokens'));

        // A fresh instance picks up the changed file
        static::assertSame(999, $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'zai/glm-5')?->get('max_input_tokens'));
    }

    public function testItDoesNotMemoizeAcrossProviders(): void
    {
        $this->writeProviderFile('zai', $this->zaiStaticData());
        $this->writeProviderFile('openai', [
            ['id' => 'gpt-4o', 'provider' => 'openai', 'max_input_tokens' => 128000],
        ]);

        $sut = $this->makeSut();
        static::assertSame('zai/glm-5', $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5')?->modelId);
        static::assertSame('gpt-4o', $sut->getModelInformation($this->makeProvider('openai'), 'gpt-4o')?->modelId);
    }
}
