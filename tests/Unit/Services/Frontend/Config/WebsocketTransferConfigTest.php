<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Config;

use App\Services\Frontend\Config\WebsocketTransferConfig;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(WebsocketTransferConfig::class)]
class WebsocketTransferConfigTest extends TestCase
{
    // =========================================================================
    // make — defaults from app.url
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com'));
        static::assertInstanceOf(WebsocketTransferConfig::class, $sut);
    }

    public function testItDerivesHostFromAppUrl(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com'));
        static::assertSame('example.com', $sut->host);
    }

    public function testItUsesPort80ForHttpUrl(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com'));
        static::assertSame(80, $sut->port);
    }

    public function testItUsesPort443ForHttpsUrl(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('https://example.com'));
        static::assertSame(443, $sut->port);
    }

    public function testItUsesExplicitPortFromUrl(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com:8080'));
        static::assertSame(8080, $sut->port);
    }

    public function testItSetsForceTlsFalseForHttp(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com'));
        static::assertFalse($sut->forceTls);
    }

    public function testItSetsForceTlsTrueForHttps(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('https://example.com'));
        static::assertTrue($sut->forceTls);
    }

    public function testItUsesDefaultPathWhenUrlHasNoPath(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com'));
        static::assertSame('/ws/transfer', $sut->path);
    }

    public function testItPrefixesPathWithAppUrlPath(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com/app'));
        static::assertSame('/app/ws/transfer', $sut->path);
    }

    public function testItStripsTrailingSlashFromPathPrefix(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com/app/'));
        static::assertSame('/app/ws/transfer', $sut->path);
    }

    public function testItSetsKeyFromReverbConfig(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com', reverbKey: 'my-reverb-key'));
        static::assertSame('my-reverb-key', $sut->key);
    }

    // =========================================================================
    // make — reverb overrides
    // =========================================================================

    public function testItOverridesHostFromReverbConfig(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com', reverbHost: 'ws.example.com'));
        static::assertSame('ws.example.com', $sut->host);
    }

    public function testItResetsPathToDefaultWhenHostIsOverridden(): void
    {
        // When host is overridden, we assume the WebSocket server is at its root.
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com/app', reverbHost: 'ws.example.com'));
        static::assertSame('/ws/transfer', $sut->path);
    }

    public function testItOverridesPortFromReverbConfig(): void
    {
        // The make() method always casts port to int via (int)$reverbPort before storing.
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com', reverbPort: '6001'));
        static::assertSame(6001, $sut->port);
    }

    public function testItOverridesSchemeFromReverbConfig(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('http://example.com', reverbScheme: 'https'));
        static::assertTrue($sut->forceTls);
    }

    public function testItSetsForceTlsFalseWhenReverbSchemeIsHttp(): void
    {
        $sut = WebsocketTransferConfig::make($this->repo('https://example.com', reverbScheme: 'http'));
        static::assertFalse($sut->forceTls);
    }

    #[DataProvider('provideTestItDerivesCorrectConfigData')]
    public function testItDerivesCorrectConfig(
        string $appUrl,
        ?string $reverbHost,
        ?string $reverbPort,
        ?string $reverbScheme,
        string $expectedHost,
        int|string $expectedPort,
        bool $expectedForceTls,
        string $expectedPath
    ): void {
        $sut = WebsocketTransferConfig::make($this->repo($appUrl, $reverbHost, $reverbPort, $reverbScheme));
        static::assertSame($expectedHost, $sut->host);
        static::assertSame($expectedPort, $sut->port);
        static::assertSame($expectedForceTls, $sut->forceTls);
        static::assertSame($expectedPath, $sut->path);
    }

    public static function provideTestItDerivesCorrectConfigData(): iterable
    {
        yield 'http url, no overrides' => [
            'http://example.com', null, null, null,
            'example.com', 80, false, '/ws/transfer',
        ];
        yield 'https url, no overrides' => [
            'https://example.com', null, null, null,
            'example.com', 443, true, '/ws/transfer',
        ];
        yield 'http with path prefix' => [
            'http://example.com/hawki', null, null, null,
            'example.com', 80, false, '/hawki/ws/transfer',
        ];
        yield 'reverb host overrides path back to root' => [
            'http://example.com/hawki', 'ws.example.com', null, null,
            'ws.example.com', 80, false, '/ws/transfer',
        ];
        yield 'explicit port in url' => [
            'http://example.com:8080', null, null, null,
            'example.com', 8080, false, '/ws/transfer',
        ];
        yield 'reverb port overrides url port' => [
            'http://example.com:8080', null, '6001', null,
            'example.com', 6001, false, '/ws/transfer',
        ];
        yield 'reverb scheme overrides http to https' => [
            'http://example.com', null, null, 'https',
            'example.com', 80, true, '/ws/transfer',
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function repo(
        string  $appUrl,
        ?string $reverbHost = null,
        ?string $reverbPort = null,
        ?string $reverbScheme = null,
        string  $reverbKey = 'test-key',
    ): Repository {
        return new Repository([
            'app' => ['url' => $appUrl],
            'reverb' => [
                'frontend' => [
                    'host' => $reverbHost,
                    'port' => $reverbPort,
                    'scheme' => $reverbScheme,
                    'key' => $reverbKey,
                ],
            ],
        ]);
    }
}
