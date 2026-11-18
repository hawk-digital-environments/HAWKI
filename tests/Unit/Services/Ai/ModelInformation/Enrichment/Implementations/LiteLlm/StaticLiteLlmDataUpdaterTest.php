<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;

use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmApiClient;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\StaticLiteLlmDataStore;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\StaticLiteLlmDataUpdater;
use App\Utils\JobMetrics;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(StaticLiteLlmDataUpdater::class)]
class StaticLiteLlmDataUpdaterTest extends TestCase
{
    private string $storageDirectory;
    private StaticLiteLlmDataStore&MockObject $dataStore;

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageDirectory = '/tmp/opencode/litellm-updater-test-' . uniqid();
        $this->dataStore = $this->createMock(StaticLiteLlmDataStore::class);
        $this->dataStore->method('getStorageDirectoryPath')->willReturn($this->storageDirectory);
        $this->dataStore->method('getProviderFilePath')->willReturnCallback(
            fn(string $providerName) => $this->storageDirectory . '/provider-' . $providerName . '.php'
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->storageDirectory);

        parent::tearDown();
    }

    private function makeSut(): StaticLiteLlmDataUpdater
    {
        return new StaticLiteLlmDataUpdater(
            new LiteLlmApiClient(app(HttpFactory::class), $this->createMock(LoggerInterface::class)),
            $this->dataStore,
            new Filesystem(),
        );
    }

    private function fakeCatalog(array $catalog): void
    {
        Http::fake(fn() => Http::response($catalog));
    }

    /** Creates a pre-existing static file that a failing run must leave untouched. */
    private function createExistingStaticFile(string $providerName): string
    {
        $filePath = $this->storageDirectory . '/provider-' . $providerName . '.php';
        (new Filesystem())->ensureDirectoryExists($this->storageDirectory);
        file_put_contents($filePath, '<?php return ["stale"];');
        return $filePath;
    }

    private function getAllOutput(JobMetrics $metrics): string
    {
        return implode("\n", [...$metrics->getErrors(), ...$metrics->getLogs()]);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(StaticLiteLlmDataUpdater::class, $this->makeSut());
    }

    // =========================================================================
    // Successful runs
    // =========================================================================

    public function testItWritesOneFilePerProviderWithNormalizedRecords(): void
    {
        $this->fakeCatalog([
            'sample_spec' => ['litellm_provider' => 'one of https://docs.litellm.ai/docs/providers'],
            'zai/glm-5' => [
                'litellm_provider' => 'zai',
                'max_input_tokens' => 200000,
            ],
            'glm-4.7' => [
                'litellm_provider' => 'zai',
                'max_input_tokens' => 128000,
            ],
            'gpt-4o' => [
                'litellm_provider' => 'openai',
                'max_input_tokens' => 128000,
            ],
        ]);

        $metrics = $this->makeSut()->run();

        static::assertFalse($metrics->hasErrors());
        static::assertSame(
            ['Models of "zai" processed' => 2, 'Models of "openai" processed' => 1],
            $metrics->getAll(),
        );

        static::assertFileExists($this->storageDirectory . '/provider-zai.php');
        static::assertFileExists($this->storageDirectory . '/provider-openai.php');

        $zaiRecords = require $this->storageDirectory . '/provider-zai.php';
        static::assertSame([
            [
                'id' => 'zai/glm-5',
                'provider' => 'zai',
                'max_input_tokens' => 200000,
            ],
            [
                'id' => 'glm-4.7',
                'provider' => 'zai',
                'max_input_tokens' => 128000,
            ],
        ], $zaiRecords);

        $openaiRecords = require $this->storageDirectory . '/provider-openai.php';
        static::assertSame([
            [
                'id' => 'gpt-4o',
                'provider' => 'openai',
                'max_input_tokens' => 128000,
            ],
        ], $openaiRecords);

        static::assertFileDoesNotExist(
            $this->storageDirectory . '/provider-one-of-httpsdocslitellmaidocsproviders.php'
        );
    }

    public function testItRemovesStaleProviderFilesOnSuccessfulRuns(): void
    {
        $staleFilePath = $this->createExistingStaticFile('stale-provider');

        $this->fakeCatalog([
            'gpt-4o' => ['litellm_provider' => 'openai'],
        ]);

        $metrics = $this->makeSut()->run();

        static::assertFalse($metrics->hasErrors());
        static::assertFileDoesNotExist($staleFilePath);
        static::assertFileExists($this->storageDirectory . '/provider-openai.php');
    }

    // =========================================================================
    // Failing runs keep existing files
    // =========================================================================

    public function testItKeepsExistingFilesAndReportsErrorWhenCatalogContainsNoModels(): void
    {
        $existingFilePath = $this->createExistingStaticFile('stale-provider');

        $this->fakeCatalog([
            'sample_spec' => ['litellm_provider' => 'one of https://docs.litellm.ai/docs/providers'],
            'fallback_generalizations' => ['openai/gpt' => 'openai'],
        ]);

        $metrics = $this->makeSut()->run();

        static::assertTrue($metrics->hasErrors());
        static::assertStringContainsString('contained no model records', $this->getAllOutput($metrics));
        static::assertStringContainsString('left untouched', $this->getAllOutput($metrics));
        static::assertFileExists($existingFilePath);
        static::assertFileDoesNotExist($this->storageDirectory . '/provider-openai.php');
    }

    public function testItKeepsExistingFilesAndReportsConnectionFailures(): void
    {
        $existingFilePath = $this->createExistingStaticFile('stale-provider');

        Http::fake(fn() => Http::failedConnection());

        $metrics = $this->makeSut()->run();

        static::assertTrue($metrics->hasErrors());
        static::assertStringContainsString('Failed to download the LiteLLM catalog', $this->getAllOutput($metrics));
        static::assertStringContainsString('left untouched', $this->getAllOutput($metrics));
        static::assertFileExists($existingFilePath);
    }

    public function testItKeepsExistingFilesAndReportsHttpErrorStatus(): void
    {
        $existingFilePath = $this->createExistingStaticFile('stale-provider');

        Http::fake(fn() => Http::response([], 404));

        $metrics = $this->makeSut()->run();

        static::assertTrue($metrics->hasErrors());
        static::assertStringContainsString('HTTP 404', $this->getAllOutput($metrics));
        static::assertStringContainsString('left untouched', $this->getAllOutput($metrics));
        static::assertFileExists($existingFilePath);
    }
}
