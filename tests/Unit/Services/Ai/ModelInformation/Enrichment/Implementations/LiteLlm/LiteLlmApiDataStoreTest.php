<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm;

use App\Models\Ai\AiProvider;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmApiClient;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmApiDataStore;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmDriverNameProviderNameMapping;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\System\Time\CarbonClockInterface;
use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(LiteLlmApiDataStore::class)]
class LiteLlmApiDataStoreTest extends TestCase
{
    private const string ETAG_V1 = '"etag-v1"';

    private CarbonImmutable $now;
    private CarbonClockInterface&MockObject $clock;
    private LoggerInterface&MockObject $logger;
    private Repository $cache;

    /** @var array<int, mixed> Upcoming fake HTTP responses, served in order. */
    private array $httpResponses = [];

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        // Anchor the mocked clock at the real current time: the cache's TTL math uses the
        // real system clock, so a fixed past date would make put() silently expire entries
        // as soon as real time moved past it
        $this->now = CarbonImmutable::now()->startOfMinute();
        $this->clock = $this->createMock(CarbonClockInterface::class);
        $this->clock->method('now')->willReturnCallback(fn(): CarbonImmutable => $this->now);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = new Repository(new ArrayStore());

        // Register the stub exactly once: every registered fake callback is invoked per
        // request, so re-faking per test would consume multiple queue items per request
        Http::fake(fn() => array_shift($this->httpResponses) ?? Http::response());
    }

    /** Queues upcoming fake HTTP responses, served in order. */
    private function queueResponses(mixed ...$responses): void
    {
        $this->httpResponses = array_values($responses);
    }

    private function makeSut(Repository|null $cache = null): LiteLlmApiDataStore
    {
        return new LiteLlmApiDataStore(
            new LiteLlmApiClient(app(HttpFactory::class), $this->logger),
            $cache ?? $this->cache,
            $this->logger,
            new LiteLlmDriverNameProviderNameMapping(),
            $this->clock,
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

    /** @return mixed A fake full-catalog response carrying the given catalog and ETag. */
    private function catalogResponse(array $catalog, string $etag = self::ETAG_V1): mixed
    {
        return Http::response($catalog, 200, ['ETag' => $etag]);
    }

    private function zaiCatalog(): array
    {
        return [
            'zai/glm-5' => ['litellm_provider' => 'zai', 'max_input_tokens' => 200000],
            'glm-4.7' => ['litellm_provider' => 'zai', 'max_input_tokens' => 128000],
        ];
    }

    private function openaiCatalog(): array
    {
        return ['gpt-4o' => ['litellm_provider' => 'openai', 'max_input_tokens' => 128000]];
    }

    private function advanceMinutes(int $minutes): void
    {
        $this->now = $this->now->addMinutes($minutes);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(LiteLlmApiDataStore::class, $this->makeSut());
    }

    // =========================================================================
    // Shared catalog, per-provider filtering
    // =========================================================================

    public function testItDownloadsTheCatalogOnceForMultipleProviders(): void
    {
        $this->queueResponses(
            $this->catalogResponse([...$this->zaiCatalog(), ...$this->openaiCatalog()]),
        );

        $zaiModel = $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');
        $openaiModel = $this->makeSut()->getModelInformation($this->makeProvider('openai'), 'gpt-4o');

        static::assertSame('zai/glm-5', $zaiModel?->modelId);
        static::assertSame('zai', $zaiModel?->get('provider'));
        static::assertSame('gpt-4o', $openaiModel?->modelId);
        static::assertSame('openai', $openaiModel?->get('provider'));

        Http::assertSentCount(1);
    }

    public function testItReturnsNullForModelsOfOtherProviders(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->openaiCatalog()),
        );

        $result = $this->makeSut()->getModelInformation($this->makeProvider('zai'), 'gpt-4o');

        static::assertNull($result);
    }

    // =========================================================================
    // ETag revalidation
    // =========================================================================

    public function testItMakesNoRequestsWhileTheLastConfirmationIsRecent(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
        );

        $sut = $this->makeSut();
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        $this->advanceMinutes(4);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'glm-4.7');

        static::assertSame('glm-4.7', $model?->modelId);
        Http::assertSentCount(1);
    }

    public function testItRevalidatesWithTheStoredEtagAfterTheRevalidationTtl(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            Http::response(null, 304),
        );

        $sut = $this->makeSut();
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        $this->advanceMinutes(6);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'glm-4.7');

        static::assertSame('glm-4.7', $model?->modelId);
        Http::assertSentCount(2);
        static::assertTrue(Http::recorded()->get(1)[0]->hasHeader('If-None-Match', self::ETAG_V1));
    }

    public function testItReplacesTheCatalogWhenUpstreamChanged(): void
    {
        $changedCatalog = [...$this->zaiCatalog(), 'zai/glm-6' => ['litellm_provider' => 'zai']];
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            $this->catalogResponse($changedCatalog, '"etag-v2"'),
        );

        $sut = $this->makeSut();
        static::assertNull($sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-6'));

        $this->advanceMinutes(6);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-6');

        static::assertSame('zai/glm-6', $model?->modelId);
        Http::assertSentCount(2);
    }

    public function testItExtendsTheDataTtlOnNotModifiedResponses(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            Http::response(null, 304),
            Http::response(null, 304),
        );

        $sut = $this->makeSut();
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        // First revalidation after 12 h: upstream confirms the catalog unchanged
        $this->advanceMinutes(12 * 60);
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        // 12 h + 6 min later: without the TTL extension this would be more than the data TTL
        // after the initial download and force a full re-download instead of a revalidation
        $this->advanceMinutes(12 * 60 + 6);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'glm-4.7');

        static::assertSame('glm-4.7', $model?->modelId);
        Http::assertSentCount(3);
        static::assertTrue(Http::recorded()->get(2)[0]->hasHeader('If-None-Match', self::ETAG_V1));
    }

    // =========================================================================
    // Overdue catalog (beyond the data TTL)
    // =========================================================================

    public function testItRevalidatesAnOverdueCatalogViaTheEtagInsteadOfADownload(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            Http::response(null, 304),
        );

        $sut = $this->makeSut();
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        // More than the data TTL without a successful confirmation: the overdue entry is
        // refreshed through the cheap conditional GET, not a full re-download
        $this->advanceMinutes(24 * 60 + 1);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        static::assertSame('zai/glm-5', $model?->modelId);
        Http::assertSentCount(2);
        static::assertTrue(Http::recorded()->get(1)[0]->hasHeader('If-None-Match', self::ETAG_V1));
    }

    public function testItReplacesAnOverdueCatalogWhenUpstreamChanged(): void
    {
        $changedCatalog = [...$this->zaiCatalog(), 'zai/glm-6' => ['litellm_provider' => 'zai']];
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            $this->catalogResponse($changedCatalog, '"etag-v2"'),
        );

        $sut = $this->makeSut();
        static::assertNull($sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-6'));

        $this->advanceMinutes(24 * 60 + 1);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-6');

        static::assertSame('zai/glm-6', $model?->modelId);
        Http::assertSentCount(2);
        static::assertTrue(Http::recorded()->get(1)[0]->hasHeader('If-None-Match', self::ETAG_V1));
    }

    // =========================================================================
    // Upstream outages
    // =========================================================================

    public function testItServesStaleDataAndLogsWhenRevalidationFails(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            Http::failedConnection(),
        );

        $this->logger
            ->expects(static::once())
            ->method('warning')
            ->with(
                'Could not revalidate the cached LiteLLM catalog, serving cached data',
                static::callback(fn(array $context): bool => isset($context['exception'])),
            );

        $sut = $this->makeSut();
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        $this->advanceMinutes(6);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'glm-4.7');

        static::assertSame('glm-4.7', $model?->modelId);
        Http::assertSentCount(2);
    }

    public function testItServesStaleDataAndLogsWhenRevalidationFailsWithHttpError(): void
    {
        // E.g. the upstream file disappeared: an HTTP error during the revalidation window
        // counts as a failed check, not as a changed catalog
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            Http::response(['error' => 'not found'], 404),
        );

        $this->logger
            ->expects(static::once())
            ->method('warning')
            ->with(
                'Could not revalidate the cached LiteLLM catalog, serving cached data',
                static::callback(fn(array $context): bool => isset($context['exception'])),
            );

        $sut = $this->makeSut();
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        $this->advanceMinutes(6);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'glm-4.7');

        static::assertSame('glm-4.7', $model?->modelId);
        Http::assertSentCount(2);
    }

    public function testItThrowsWhenTheUpstreamIsUnreachableBeyondTheDataTtl(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            Http::failedConnection(),
        );

        $sut = $this->makeSut();
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        // More than the data TTL without a successful confirmation: the cached catalog may
        // no longer be served, so the failure propagates (caller falls back to static files)
        $this->advanceMinutes(24 * 60 + 1);

        $this->expectException(\Illuminate\Http\Client\ConnectionException::class);
        $this->expectExceptionMessage('Could not resolve host');

        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');
    }

    // =========================================================================
    // In-memory memoization
    // =========================================================================

    public function testItServesRepeatedLookupsFromTheInMemoryMemo(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
        );

        $cache = $this->countingCache();
        $sut = $this->makeSut($cache);

        static::assertSame('zai/glm-5', $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5')?->modelId);
        static::assertSame('glm-4.7', $sut->getModelInformation($this->makeProvider('zai'), 'glm-4.7')?->modelId);
        static::assertSame('zai/glm-5', $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5')?->modelId);

        // One download and one cache read serve all lookups: the repeats hit the in-memory memo
        static::assertSame(1, $cache->getCalls);
        Http::assertSentCount(1);
    }

    public function testItRereadsTheMemoAfterItExpired(): void
    {
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            Http::response(null, 304),
        );

        $cache = $this->countingCache();
        $sut = $this->makeSut($cache);
        $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-5');

        // Beyond the memo TTL (which mirrors the revalidation window): full path again,
        // re-reading the cache and revalidating with the stored ETag
        $this->advanceMinutes(6);
        $model = $sut->getModelInformation($this->makeProvider('zai'), 'glm-4.7');

        static::assertSame('glm-4.7', $model?->modelId);
        static::assertSame(2, $cache->getCalls);
        Http::assertSentCount(2);
        static::assertTrue(Http::recorded()->get(1)[0]->hasHeader('If-None-Match', self::ETAG_V1));
    }

    // =========================================================================
    // Cache clearing
    // =========================================================================

    public function testItReDownloadsTheCatalogAfterTheCacheIsCleared(): void
    {
        $expandedCatalog = [...$this->zaiCatalog(), 'zai/glm-6' => ['litellm_provider' => 'zai']];
        $this->queueResponses(
            $this->catalogResponse($this->zaiCatalog()),
            $this->catalogResponse($expandedCatalog, '"etag-v2"'),
        );

        $sut = $this->makeSut();
        static::assertNull($sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-6'));

        $sut->clearModelInformationCache();

        $model = $sut->getModelInformation($this->makeProvider('zai'), 'zai/glm-6');

        static::assertSame('zai/glm-6', $model?->modelId);
        Http::assertSentCount(2);
    }

    /**
     * A cache repository that counts get() calls, so tests can assert the in-memory
     * memo skips repeated cache reads.
     *
     * The concrete instance exposes a public `getCalls` counter.
     */
    private function countingCache(): Repository
    {
        return new class(new ArrayStore()) extends Repository {
            public int $getCalls = 0;

            public function get($key, mixed $default = null): mixed
            {
                $this->getCalls++;
                return parent::get($key, $default);
            }
        };
    }
}
