<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\Config;

use App\Services\Frontend\Config\TransferConfig;
use App\Services\Frontend\Config\WebsocketTransferConfig;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TransferConfig::class)]
class TransferConfigTest extends TestCase
{
    // =========================================================================
    // make
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = TransferConfig::make($this->repo('http://example.com'));
        static::assertInstanceOf(TransferConfig::class, $sut);
    }

    public function testItReadsBaseUrlFromAppUrl(): void
    {
        $sut = TransferConfig::make($this->repo('http://example.com'));
        static::assertSame('http://example.com', $sut->baseUrl);
    }

    public function testItPopulatesWebsocketConfig(): void
    {
        $sut = TransferConfig::make($this->repo('http://example.com'));
        static::assertInstanceOf(WebsocketTransferConfig::class, $sut->websocket);
    }

    // =========================================================================
    // publicKey
    // =========================================================================

    public function testItReturnsCorrectPublicKey(): void
    {
        static::assertSame('transfer', TransferConfig::publicKey());
    }

    // =========================================================================
    // toPublicArray — unauthenticated
    // =========================================================================

    public function testItToPublicArrayAlwaysContainsBaseUrl(): void
    {
        $sut = TransferConfig::make($this->repo('http://example.com'));
        $result = $sut->toPublicArray($this->guestRequest());
        static::assertArrayHasKey('baseUrl', $result);
        static::assertSame('http://example.com', $result['baseUrl']);
    }

    public function testItToPublicArrayOmitsWebsocketForGuests(): void
    {
        $sut = TransferConfig::make($this->repo('http://example.com'));
        $result = $sut->toPublicArray($this->guestRequest());
        static::assertArrayNotHasKey('websocket', $result);
    }

    // =========================================================================
    // toPublicArray — authenticated
    // =========================================================================

    public function testItToPublicArrayIncludesWebsocketForAuthenticatedUsers(): void
    {
        $sut = TransferConfig::make($this->repo('http://example.com'));
        $result = $sut->toPublicArray($this->authenticatedRequest());
        static::assertArrayHasKey('websocket', $result);
    }

    public function testItToPublicArrayWebsocketContainsExpectedKeys(): void
    {
        $sut = TransferConfig::make($this->repo('http://example.com'));
        $result = $sut->toPublicArray($this->authenticatedRequest());
        static::assertArrayHasKey('key', $result['websocket']);
        static::assertArrayHasKey('host', $result['websocket']);
        static::assertArrayHasKey('port', $result['websocket']);
        static::assertArrayHasKey('forceTls', $result['websocket']);
        static::assertArrayHasKey('path', $result['websocket']);
    }

    public function testItToPublicArrayWebsocketHostMatchesConfig(): void
    {
        $sut = TransferConfig::make($this->repo('https://ws.example.com'));
        $result = $sut->toPublicArray($this->authenticatedRequest());
        static::assertSame('ws.example.com', $result['websocket']['host']);
    }

    public function testItToPublicArrayIsNotNull(): void
    {
        $sut = TransferConfig::make($this->repo('http://example.com'));
        static::assertNotNull($sut->toPublicArray($this->guestRequest()));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function repo(string $appUrl): Repository
    {
        return new Repository([
            'app' => ['url' => $appUrl],
            'reverb' => ['frontend' => ['host' => null, 'port' => null, 'scheme' => null, 'key' => 'test-key']],
        ]);
    }

    private function guestRequest(): Request
    {
        // user() returns null on a plain request with no user resolver set.
        return Request::create('/');
    }

    private function authenticatedRequest(): Request
    {
        $request = Request::create('/');
        $request->setUserResolver(fn() => new \stdClass());
        return $request;
    }
}
