<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent;

use App\Services\ExternalContent\FavIconProxy;
use App\Services\ExternalContent\ProxyClient;
use App\Services\ExternalContent\Values\ResolvedExternalImage;
use App\Services\System\Time\Clock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(FavIconProxy::class)]
class FavIconProxyTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return MockObject&Repository */
    private function makeCache(bool $invokeCallback = true): MockObject
    {
        $cache = $this->createMock(Repository::class);
        if ($invokeCallback) {
            $cache->method('remember')
                ->willReturnCallback(fn($key, $ttl, $callback) => $callback());
        }
        return $cache;
    }

    /** @return MockObject&ProxyClient */
    private function makeProxyClient(): MockObject
    {
        return $this->createMock(ProxyClient::class);
    }

    /** @return MockObject&Response */
    private function makeImageResponse(
        string $contentType = 'image/x-icon',
        string $body = 'icon-bytes',
        bool $successful = true,
    ): MockObject {
        $response = $this->createMock(Response::class);
        $response->method('successful')->willReturn($successful);
        $response->method('header')->with('Content-Type')->willReturn($contentType);
        $response->method('body')->willReturn($body);
        return $response;
    }

    private function makeSut(
        ProxyClient $client = null,
        Repository $cache = null,
        LoggerInterface $logger = null,
    ): FavIconProxy {
        return new FavIconProxy(
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
        static::assertInstanceOf(FavIconProxy::class, $sut);
    }

    // =========================================================================
    // getFaviconOf — happy path
    // =========================================================================

    public function testItGetFaviconOfReturnsIconFromGoogleService(): void
    {
        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')
            ->willReturn($this->makeImageResponse('image/x-icon', 'icon-bytes'));

        $sut = $this->makeSut(client: $client);
        $result = $sut->getFaviconOf('https://example.com/page');

        static::assertInstanceOf(ResolvedExternalImage::class, $result);
        static::assertSame('image/x-icon', $result->mimeType);
        static::assertSame('icon-bytes', $result->content);
        static::assertFalse($result->isFallback);
    }

    public function testItGetFaviconOfUsesCachedResult(): void
    {
        $expected = new ResolvedExternalImage('cached-icon', 'image/png');
        $cache = $this->createMock(Repository::class);
        $cache->method('remember')->willReturn($expected);

        $sut = $this->makeSut(cache: $cache);

        static::assertSame($expected, $sut->getFaviconOf('https://example.com/page'));
    }

    public function testItGetFaviconOfRequestsFaviconForCorrectDomain(): void
    {
        $client = $this->makeProxyClient();
        $client->expects(static::once())
            ->method('fetchOrThrow')
            ->with(
                // Google favicon URL contains the extracted domain, not the full path
                static::stringContains('domain=example.com'),
                static::anything()
            )
            ->willReturn($this->makeImageResponse());

        $sut = $this->makeSut(client: $client);
        $sut->getFaviconOf('https://example.com/some/path?query=value');
    }

    // =========================================================================
    // getFaviconOf — fallback behaviour
    // =========================================================================

    public function testItGetFaviconOfReturnsFallbackSvgWhenFetchFails(): void
    {
        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')
            ->willThrowException(new \RuntimeException('Connection error'));

        $sut = $this->makeSut(client: $client);
        $result = $sut->getFaviconOf('https://example.com/page');

        static::assertInstanceOf(ResolvedExternalImage::class, $result);
        static::assertTrue($result->isFallback);
        static::assertSame('image/svg+xml', $result->mimeType);
    }

    public function testItGetFaviconOfReturnsFallbackWhenGoogleReturnsNonImageContentType(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('successful')->willReturn(true);
        $response->method('header')->with('Content-Type')->willReturn('text/html');

        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')->willReturn($response);

        $sut = $this->makeSut(client: $client);
        $result = $sut->getFaviconOf('https://example.com/page');

        static::assertInstanceOf(ResolvedExternalImage::class, $result);
        static::assertTrue($result->isFallback);
        static::assertSame('image/svg+xml', $result->mimeType);
    }

    public function testItGetFaviconOfLogsFetchErrors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('warning')
            ->with(
                static::stringContains('Error fetching favicon for URL'),
                static::arrayHasKey('exception')
            );

        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')
            ->willThrowException(new \RuntimeException('Network error'));

        $sut = $this->makeSut(client: $client, logger: $logger);
        $sut->getFaviconOf('https://example.com/page');
    }

    public function testItGetFaviconOfFallbackContainsHawkiGlobeSvg(): void
    {
        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')
            ->willThrowException(new \RuntimeException('No icon'));

        $sut = $this->makeSut(client: $client);
        $result = $sut->getFaviconOf('https://example.com/');

        static::assertStringContainsString('<svg', $result->content);
    }
}
