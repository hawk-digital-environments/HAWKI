<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent;

use App\Services\ExternalContent\ExternalImageProxy;
use App\Services\ExternalContent\ProxyClient;
use App\Services\ExternalContent\Values\ResolvedExternalImage;
use App\Services\System\Time\Clock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(ExternalImageProxy::class)]
class ExternalImageProxyTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @return MockObject&Repository
     */
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
        string $contentType = 'image/png',
        string $body = 'binary-image-data'
    ): MockObject {
        $response = $this->createMock(Response::class);
        $response->method('header')->with('Content-Type')->willReturn($contentType);
        $response->method('body')->willReturn($body);
        return $response;
    }

    private function makeSut(
        ProxyClient $client = null,
        Repository $cache = null,
        LoggerInterface $logger = null,
    ): ExternalImageProxy {
        return new ExternalImageProxy(
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
        static::assertInstanceOf(ExternalImageProxy::class, $sut);
    }

    // =========================================================================
    // get — happy path
    // =========================================================================

    public function testItGetReturnsImageFromSuccessfulFetch(): void
    {
        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')
            ->willReturn($this->makeImageResponse('image/jpeg', 'jpeg-binary'));

        $sut = $this->makeSut(client: $client);
        $result = $sut->get('https://example.com/photo.jpg');

        static::assertInstanceOf(ResolvedExternalImage::class, $result);
        static::assertSame('image/jpeg', $result->mimeType);
        static::assertSame('jpeg-binary', $result->content);
        static::assertFalse($result->isFallback);
    }

    public function testItGetUsesCachedResult(): void
    {
        $expected = new ResolvedExternalImage('cached', 'image/png');
        $cache = $this->createMock(Repository::class);
        $cache->method('remember')->willReturn($expected);

        $sut = $this->makeSut(cache: $cache);

        static::assertSame($expected, $sut->get('https://example.com/photo.jpg'));
    }

    // =========================================================================
    // get — fallback behaviour
    // =========================================================================

    public function testItGetReturnsFallbackImageWhenFetchFails(): void
    {
        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $sut = $this->makeSut(client: $client);
        $result = $sut->get('https://example.com/missing.jpg');

        static::assertInstanceOf(ResolvedExternalImage::class, $result);
        static::assertTrue($result->isFallback);
        static::assertSame('image/png', $result->mimeType);
    }

    public function testItGetReturnsFallbackImageWhenResponseIsNotAnImage(): void
    {
        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')
            ->willReturn($this->makeImageResponse('text/html', '<html/>'));

        $sut = $this->makeSut(client: $client);
        $result = $sut->get('https://example.com/page.html');

        static::assertInstanceOf(ResolvedExternalImage::class, $result);
        static::assertTrue($result->isFallback);
    }

    public function testItGetLogsFetchErrors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('warning')
            ->with(
                static::stringContains('Error fetching image from URL'),
                static::arrayHasKey('exception')
            );

        $client = $this->makeProxyClient();
        $client->method('fetchOrThrow')
            ->willThrowException(new \RuntimeException('Network error'));

        $sut = $this->makeSut(client: $client, logger: $logger);
        $sut->get('https://example.com/photo.jpg');
    }

    // =========================================================================
    // makeFallbackImage
    // =========================================================================

    public function testItMakeFallbackImageReturnsPngWithFallbackFlag(): void
    {
        $sut = $this->makeSut();
        $result = $sut->makeFallbackImage();

        static::assertInstanceOf(ResolvedExternalImage::class, $result);
        static::assertSame('image/png', $result->mimeType);
        static::assertTrue($result->isFallback);
        static::assertNotEmpty($result->content);
    }
}
