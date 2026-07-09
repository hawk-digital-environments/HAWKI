<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Http;

use App\Services\System\Http\Exceptions\SsrfBlockedException;
use App\Services\System\Http\Exceptions\TooManyRedirectsException;
use App\Services\System\Http\SsrfSafeGetterMacro;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SsrfSafeGetterMacro::class)]
class SsrfSafeGetterMacroTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return MockObject&PendingRequest */
    private function makePendingRequest(array $guzzleOptions = []): MockObject
    {
        $mock = $this->createMock(PendingRequest::class);
        $mock->method('getOptions')->willReturn($guzzleOptions);
        $mock->method('withoutRedirecting')->willReturnSelf();
        return $mock;
    }

    /** @return MockObject&Response */
    private function makeResponse(int $status = 200, string $location = ''): MockObject
    {
        $mock = $this->createMock(Response::class);
        $mock->method('status')->willReturn($status);
        $mock->method('header')->with('Location')->willReturn($location);
        return $mock;
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    public function testItReturnsResponseForPublicIpUrl(): void
    {
        $response = $this->makeResponse(200);
        $request = $this->makePendingRequest();
        $request->method('get')->willReturn($response);

        $result = SsrfSafeGetterMacro::execute($request, 'http://1.1.1.1/');

        static::assertSame($response, $result);
    }

    public function testItFollowsRedirectsAndReturnsLastResponse(): void
    {
        $redirect = $this->makeResponse(301, 'http://1.1.1.1/final');
        $final = $this->makeResponse(200);

        $request = $this->makePendingRequest();
        $request->method('get')->willReturnOnConsecutiveCalls($redirect, $final);

        $result = SsrfSafeGetterMacro::execute($request, 'http://1.1.1.1/original');

        static::assertSame($final, $result);
    }

    public function testItOnlySendsQueryParamsOnInitialRequest(): void
    {
        $capturedQueries = [];
        $redirect = $this->makeResponse(301, 'http://1.1.1.1/final');
        $final = $this->makeResponse(200);

        $request = $this->makePendingRequest();
        $request->method('get')->willReturnCallback(
            static function (string $url, mixed $query) use ($redirect, $final, &$capturedQueries) {
                $capturedQueries[] = $query;
                return count($capturedQueries) === 1 ? $redirect : $final;
            }
        );

        SsrfSafeGetterMacro::execute($request, 'http://1.1.1.1/original', ['q' => 'test']);

        static::assertSame(['q' => 'test'], $capturedQueries[0]);
        static::assertNull($capturedQueries[1]);
    }

    // =========================================================================
    // Redirect limit
    // =========================================================================

    public function testItDefaultsToFiveMaxRedirects(): void
    {
        $request = $this->makePendingRequest(); // no allow_redirects in options
        $request->method('get')->willReturn($this->makeResponse(301, 'http://1.1.1.1/hop'));

        static::expectException(TooManyRedirectsException::class);
        static::expectExceptionMessage(sprintf('Failed to fetch "%s" after %d redirects.', 'http://1.1.1.1/start', 5));

        SsrfSafeGetterMacro::execute($request, 'http://1.1.1.1/start');
    }

    public function testItReadsMaxRedirectsFromGuzzleOptions(): void
    {
        $request = $this->makePendingRequest(['allow_redirects' => ['max' => 2]]);
        $request->method('get')->willReturn($this->makeResponse(301, 'http://1.1.1.1/hop'));

        static::expectException(TooManyRedirectsException::class);
        static::expectExceptionMessage(sprintf('Failed to fetch "%s" after %d redirects.', 'http://1.1.1.1/start', 2));

        SsrfSafeGetterMacro::execute($request, 'http://1.1.1.1/start');
    }

    // =========================================================================
    // SSRF URL validation — blocked cases
    // =========================================================================

    #[DataProvider('provideTestItBlocksSsrfUrlsData')]
    public function testItBlocksSsrfUrls(string $url, string $expectedMessage): void
    {
        static::expectException(SsrfBlockedException::class);
        static::expectExceptionMessage($expectedMessage);

        SsrfSafeGetterMacro::execute($this->makePendingRequest(), $url);
    }

    public static function provideTestItBlocksSsrfUrlsData(): iterable
    {
        yield 'malformed url' => [
            'not-a-url',
            'Malformed URL: "not-a-url".',
        ];

        yield 'ftp scheme' => [
            'ftp://1.1.1.1/',
            'Only http and https URLs are allowed, got: "ftp".',
        ];

        yield 'credentials in url' => [
            'http://user:pass@1.1.1.1/',
            'Credentials in URL are not allowed.',
        ];

        yield 'loopback ip 127.0.0.1' => [
            'http://127.0.0.1/',
            'URL host "127.0.0.1" resolves to a non-public address.',
        ];

        yield 'private ip 10.x.x.x' => [
            'http://10.0.0.1/',
            'URL host "10.0.0.1" resolves to a non-public address.',
        ];

        yield 'private ip 192.168.x.x' => [
            'http://192.168.1.1/',
            'URL host "192.168.1.1" resolves to a non-public address.',
        ];

        yield 'link-local ip 169.254.x.x' => [
            'http://169.254.1.1/',
            'URL host "169.254.1.1" resolves to a non-public address.',
        ];

        // Dotless decimal encoding of 127.0.0.1 — bypass attempt
        yield 'dotless decimal encoding of loopback' => [
            'http://2130706433/',
            'URL host "2130706433" resolves to a non-public address.',
        ];

        // Octal encoding of 127.0.0.1 — bypass attempt
        yield 'octal encoding of loopback' => [
            'http://0177.0.0.1/',
            'URL host "0177.0.0.1" resolves to a non-public address.',
        ];

        // Hex encoding of 127.0.0.1 — bypass attempt
        yield 'hex encoding of loopback' => [
            'http://0x7f.0.0.1/',
            'URL host "0x7f.0.0.1" resolves to a non-public address.',
        ];

        // IPv4-mapped IPv6 encoding of 127.0.0.1 — bypass attempt
        yield 'ipv4-mapped ipv6 loopback' => [
            'http://[::ffff:127.0.0.1]/',
            'URL host "::ffff:127.0.0.1" resolves to a non-public address.',
        ];
    }

    // =========================================================================
    // SSRF validation on redirect hops
    // =========================================================================

    public function testItBlocksSsrfUrlInRedirectLocation(): void
    {
        // First hop redirects to a private IP literal
        $redirect = $this->makeResponse(302, 'http://127.0.0.1/internal');
        $request = $this->makePendingRequest();
        $request->method('get')->willReturn($redirect);

        static::expectException(SsrfBlockedException::class);
        static::expectExceptionMessage('URL host "127.0.0.1" resolves to a non-public address.');

        SsrfSafeGetterMacro::execute($request, 'http://1.1.1.1/start');
    }
}
