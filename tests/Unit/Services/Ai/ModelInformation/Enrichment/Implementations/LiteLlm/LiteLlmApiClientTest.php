<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;

use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(LiteLlmApiClient::class)]
class LiteLlmApiClientTest extends TestCase
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

    private function makeSut(): LiteLlmApiClient
    {
        return new LiteLlmApiClient(app(HttpFactory::class), $this->logger);
    }

    private function fakeCatalog(array $catalog): void
    {
        Http::fake(fn() => Http::response($catalog));
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(LiteLlmApiClient::class, $this->makeSut());
    }

    // =========================================================================
    // fetchRawCatalog / fetchData
    // =========================================================================

    public function testItFetchesAndNormalizesRecords(): void
    {
        $this->fakeCatalog([
            'gpt-4o' => [
                'litellm_provider' => 'openai',
                'max_input_tokens' => 128000,
                'max_output_tokens' => 16384,
            ],
            'zai/glm-5' => [
                'litellm_provider' => 'zai',
                'max_input_tokens' => 200000,
            ],
        ]);

        $rows = [...$this->makeSut()->fetchData()];

        static::assertSame([
            [
                'id' => 'gpt-4o',
                'provider' => 'openai',
                'max_input_tokens' => 128000,
                'max_output_tokens' => 16384,
            ],
            [
                'id' => 'zai/glm-5',
                'provider' => 'zai',
                'max_input_tokens' => 200000,
            ],
        ], $rows);
    }

    public function testItSkipsKnownNonModelKeysSilently(): void
    {
        $this->fakeCatalog([
            'sample_spec' => [
                'litellm_provider' => 'one of https://docs.litellm.ai/docs/providers',
                'mode' => 'one of: chat, embedding, completion',
            ],
            'fallback_generalizations' => [
                'openai/gpt' => 'openai',
            ],
            'gpt-4o' => [
                'litellm_provider' => 'openai',
            ],
        ]);

        $this->logger->expects(static::never())->method('debug');

        $rows = [...$this->makeSut()->fetchData()];

        static::assertSame([['id' => 'gpt-4o', 'provider' => 'openai']], $rows);
    }

    public function testItLogsDebugWhenSkippingUnexpectedEntries(): void
    {
        $this->fakeCatalog([
            'not-an-array' => 'nope',
            'missing-provider' => ['max_input_tokens' => 4096],
            'empty-provider' => ['litellm_provider' => ''],
            'non-string-provider' => ['litellm_provider' => 42],
        ]);

        $skippedEntries = [];
        $this->logger
            ->expects(static::exactly(4))
            ->method('debug')
            ->willReturnCallback(function (string $message, array $context = []) use (&$skippedEntries): void {
                $skippedEntries[] = $context['key'];
            });

        $rows = [...$this->makeSut()->fetchData()];

        static::assertSame([], $rows);
        static::assertSame(
            ['not-an-array', 'missing-provider', 'empty-provider', 'non-string-provider'],
            $skippedEntries,
        );
    }

    public function testItFiltersRecordsByProviderName(): void
    {
        $this->fakeCatalog([
            'gpt-4o' => ['litellm_provider' => 'openai'],
            'zai/glm-5' => ['litellm_provider' => 'zai'],
            'glm-4.7' => ['litellm_provider' => 'zai'],
        ]);

        $rows = [...$this->makeSut()->fetchData('zai')];

        static::assertSame([
            ['id' => 'zai/glm-5', 'provider' => 'zai'],
            ['id' => 'glm-4.7', 'provider' => 'zai'],
        ], $rows);
    }

    public function testItReturnsAnEmptyCatalogWhenTheResponseIsEmpty(): void
    {
        $this->fakeCatalog([]);

        $fetch = $this->makeSut()->fetchRawCatalog();

        static::assertFalse($fetch->notModified);
        static::assertSame([], $fetch->catalog);
    }

    public function testItExtractsTheEtagFromFullResponses(): void
    {
        Http::fake(fn() => Http::response(['gpt-4o' => ['litellm_provider' => 'openai']], 200, ['ETag' => '"etag-v1"']));

        $fetch = $this->makeSut()->fetchRawCatalog();

        static::assertFalse($fetch->notModified);
        static::assertSame('"etag-v1"', $fetch->etag);
        static::assertSame(['gpt-4o' => ['litellm_provider' => 'openai']], $fetch->catalog);
    }

    public function testItSendsTheStoredEtagAndDetectsNotModified(): void
    {
        Http::fake(fn() => Http::response(null, 304));

        $fetch = $this->makeSut()->fetchRawCatalog('"etag-v1"');

        static::assertTrue($fetch->notModified);
        static::assertSame([], $fetch->catalog);

        Http::assertSent(fn($request) => $request->hasHeader('If-None-Match', '"etag-v1"'));
    }

    public function testItThrowsOnConnectionFailure(): void
    {
        Http::fake(fn() => Http::failedConnection('connection refused'));

        $this->expectException(ConnectionException::class);

        $this->makeSut()->fetchRawCatalog();
    }

    public function testItThrowsOnHttpErrors(): void
    {
        Http::fake(fn() => Http::response([], 404));

        $this->expectException(RequestException::class);

        $this->makeSut()->fetchRawCatalog();
    }
}
