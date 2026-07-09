<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent;

use App\Services\ExternalContent\ProxyClient;
use App\Services\ExternalContent\Values\WebsiteMetadata;
use App\Services\ExternalContent\WebsiteMetadataLoader;
use App\Services\System\Time\Clock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(WebsiteMetadataLoader::class)]
class WebsiteMetadataLoaderTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return MockObject&Repository */
    private function makeCache(): MockObject
    {
        $cache = $this->createMock(Repository::class);
        $cache->method('remember')
            ->willReturnCallback(fn($key, $ttl, $callback) => $callback());
        return $cache;
    }

    /** @return MockObject&UrlGenerator */
    private function makeUrlGenerator(): MockObject
    {
        $generator = $this->createMock(UrlGenerator::class);
        $generator->method('route')
            ->willReturnCallback(function (string $name, array $params): string {
                $param = array_values($params)[0] ?? '';
                return match ($name) {
                    'api.link-preview.favicon' => '/favicon?url=' . $param,
                    'api.link-preview.image' => '/image?url=' . $param,
                    default => '/' . $name,
                };
            });
        return $generator;
    }

    /** @return MockObject&ProxyClient */
    private function makeProxyClient(string $htmlBody = ''): MockObject
    {
        $response = $this->createMock(Response::class);
        $response->method('body')->willReturn($htmlBody);

        $client = $this->createMock(ProxyClient::class);
        $client->method('fetchOrThrow')->willReturn($response);
        return $client;
    }

    private function makeSut(
        UrlGenerator $urlGenerator = null,
        ProxyClient $client = null,
        Repository $cache = null,
        LoggerInterface $logger = null,
    ): WebsiteMetadataLoader {
        return new WebsiteMetadataLoader(
            urlGenerator: $urlGenerator ?? $this->makeUrlGenerator(),
            client: $client ?? $this->makeProxyClient(),
            cache: $cache ?? $this->makeCache(),
            logger: $logger ?? $this->createMock(LoggerInterface::class),
            clock: new Clock(new \DateTimeImmutable('2026-01-01 12:00:00')),
        );
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(WebsiteMetadataLoader::class, $sut);
    }

    // =========================================================================
    // load — title extraction
    // =========================================================================

    public function testItExtractsTitleFromTitleTag(): void
    {
        $html = '<html><head><title>Page Title</title></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        static::assertSame('Page Title', $sut->load('https://example.com/')->title);
    }

    public function testItFallsBackToOgTitleWhenTitleTagIsMissing(): void
    {
        $html = '<html><head><meta property="og:title" content="OG Title"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        static::assertSame('OG Title', $sut->load('https://example.com/')->title);
    }

    public function testItFallsBackToTwitterTitleWhenOgTitleIsMissing(): void
    {
        $html = '<html><head><meta name="twitter:title" content="Twitter Title"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        static::assertSame('Twitter Title', $sut->load('https://example.com/')->title);
    }

    public function testItFallsBackToDomainDerivedTitleWhenNoTitleTagsExist(): void
    {
        $html = '<html><head></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        // www. and TLD stripped, then headline-cased
        static::assertSame('Example Site', $sut->load('https://www.example-site.com/page')->title);
    }

    // =========================================================================
    // load — description extraction
    // =========================================================================

    public function testItExtractsDescriptionFromOgDescription(): void
    {
        $html = '<html><head><meta property="og:description" content="OG Desc"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        static::assertSame('OG Desc', $sut->load('https://example.com/')->description);
    }

    public function testItFallsBackToTwitterDescription(): void
    {
        $html = '<html><head><meta name="twitter:description" content="Twitter Desc"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        static::assertSame('Twitter Desc', $sut->load('https://example.com/')->description);
    }

    public function testItFallsBackToMetaDescription(): void
    {
        $html = '<html><head><meta name="description" content="Meta Desc"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        static::assertSame('Meta Desc', $sut->load('https://example.com/')->description);
    }

    public function testItReturnsNullDescriptionWhenNoDescriptionTagExists(): void
    {
        $html = '<html><head><title>Title</title></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        static::assertNull($sut->load('https://example.com/')->description);
    }

    // =========================================================================
    // load — image URL extraction
    // =========================================================================

    public function testItWrapsOgImageInProxyUrl(): void
    {
        $html = '<html><head><meta property="og:image" content="https://example.com/img.jpg"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        $result = $sut->load('https://example.com/');

        static::assertStringStartsWith('/image?url=', $result->image);
        static::assertStringContainsString('example.com/img.jpg', $result->image);
    }

    public function testItFallsBackToTwitterImageWhenOgImageIsMissing(): void
    {
        $html = '<html><head><meta name="twitter:image" content="https://example.com/twitter.jpg"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        $result = $sut->load('https://example.com/');

        static::assertStringStartsWith('/image?url=', $result->image);
        static::assertStringContainsString('twitter.jpg', $result->image);
    }

    public function testItResolvesRelativeImageUrlsToAbsolute(): void
    {
        $html = '<html><head><meta property="og:image" content="/images/photo.jpg"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        $result = $sut->load('https://example.com/page');

        // Relative URL resolved to https://example.com/images/photo.jpg before proxying
        static::assertStringContainsString('example.com/images/photo.jpg', $result->image);
    }

    public function testItUsesFallbackImageWhenNoImageTagExists(): void
    {
        $html = '<html><head><title>Title</title></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        $result = $sut->load('https://example.com/');

        // Fallback uses 'fallback_' prefix as image identifier
        static::assertStringStartsWith('/image?url=fallback_', $result->image);
    }

    // =========================================================================
    // load — favicon URL extraction
    // =========================================================================

    public function testItWrapsFaviconInProxyUrl(): void
    {
        $html = '<html><head><link rel="icon" href="/favicon.ico"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        $result = $sut->load('https://example.com/');

        static::assertStringStartsWith('/favicon?url=', $result->favicon);
        static::assertStringContainsString('example.com', $result->favicon);
    }

    public function testItFallsBackToShortcutIconLink(): void
    {
        $html = '<html><head><link rel="shortcut icon" href="/shortcut.ico"/></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        $result = $sut->load('https://example.com/');

        static::assertStringStartsWith('/favicon?url=', $result->favicon);
        static::assertStringContainsString('example.com', $result->favicon);
    }

    // =========================================================================
    // load — domain and URL
    // =========================================================================

    public function testItExtractsDomainFromUrl(): void
    {
        $html = '<html><head><title>Page</title></head><body></body></html>';
        $sut = $this->makeSut(client: $this->makeProxyClient($html));

        $result = $sut->load('https://example.com/page/path');

        static::assertSame('example.com', $result->domain);
        static::assertSame('https://example.com/page/path', $result->url);
    }

    // =========================================================================
    // load — cache
    // =========================================================================

    public function testItReturnsCachedMetadataWithoutFetching(): void
    {
        $expected = new WebsiteMetadata(
            url: 'https://example.com/',
            domain: 'example.com',
            title: 'Cached Page',
        );
        $cache = $this->createMock(Repository::class);
        $cache->method('remember')->willReturn($expected);

        $client = $this->createMock(ProxyClient::class);
        $client->expects(static::never())->method('fetchOrThrow');

        $sut = $this->makeSut(cache: $cache, client: $client);

        static::assertSame($expected, $sut->load('https://example.com/'));
    }

    // =========================================================================
    // load — fallback on fetch error
    // =========================================================================

    public function testItReturnsFallbackMetadataWhenFetchFails(): void
    {
        $client = $this->createMock(ProxyClient::class);
        $client->method('fetchOrThrow')->willThrowException(new \RuntimeException('Network error'));

        $sut = $this->makeSut(client: $client);
        $result = $sut->load('https://www.example-site.com/');

        static::assertInstanceOf(WebsiteMetadata::class, $result);
        static::assertTrue($result->isFallback);
        static::assertSame('Example Site', $result->title);
        static::assertSame('www.example-site.com', $result->domain);
        static::assertNull($result->description);
    }

    public function testItLogsFetchErrors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('warning')
            ->with(
                static::stringContains('Error fetching metadata from URL'),
                static::arrayHasKey('exception')
            );

        $client = $this->createMock(ProxyClient::class);
        $client->method('fetchOrThrow')->willThrowException(new \RuntimeException('Network error'));

        $sut = $this->makeSut(client: $client, logger: $logger);
        $sut->load('https://example.com/');
    }
}
