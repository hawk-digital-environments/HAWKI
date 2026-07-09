<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent;

use App\Services\ExternalContent\Exceptions\FailedToFetchUrlException;
use App\Services\ExternalContent\ProxyClient;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Services\ExternalContent\ProxyClientTestFixtures\TestPendingRequest;

#[CoversClass(ProxyClient::class)]
class ProxyClientTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a TestPendingRequest mock that stubs the fluent chain and getSsrfSafe.
     *
     * TestPendingRequest declares getSsrfSafe() as a real method so PHPUnit can mock it
     * without the deprecated addMethods() API.
     *
     * @return MockObject&TestPendingRequest
     */
    private function makePendingRequest(): MockObject
    {
        $mock = $this->getMockBuilder(TestPendingRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['timeout', 'withHeaders', 'getSsrfSafe'])
            ->getMock();

        $mock->method('timeout')->willReturnSelf();
        $mock->method('withHeaders')->willReturnSelf();

        return $mock;
    }

    /** @return MockObject&Response */
    private function makeResponse(bool $successful): MockObject
    {
        $mock = $this->createMock(Response::class);
        $mock->method('successful')->willReturn($successful);
        return $mock;
    }

    private function makeSut(TestPendingRequest $http): ProxyClient
    {
        return new ProxyClient($http);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut($this->makePendingRequest());
        static::assertInstanceOf(ProxyClient::class, $sut);
    }

    // =========================================================================
    // fetchOrThrow — happy path
    // =========================================================================

    public function testItFetchOrThrowReturnsResponseOnSuccess(): void
    {
        $response = $this->makeResponse(true);
        $http = $this->makePendingRequest();
        $http->method('getSsrfSafe')->willReturn($response);

        $sut = $this->makeSut($http);
        $result = $sut->fetchOrThrow('https://example.com/', 3);

        static::assertSame($response, $result);
    }

    public function testItFetchOrThrowAppliesTimeout(): void
    {
        $http = $this->makePendingRequest();
        $http->method('getSsrfSafe')->willReturn($this->makeResponse(true));
        $http->expects(static::once())
            ->method('timeout')
            ->with(7)
            ->willReturnSelf();

        $sut = $this->makeSut($http);
        $sut->fetchOrThrow('https://example.com/', 7);
    }

    public function testItFetchOrThrowSendsHawkiBotUserAgent(): void
    {
        $http = $this->makePendingRequest();
        $http->method('getSsrfSafe')->willReturn($this->makeResponse(true));
        $http->expects(static::once())
            ->method('withHeaders')
            ->with(static::callback(function (array $headers): bool {
                return isset($headers['User-Agent'])
                    && str_contains($headers['User-Agent'], 'HAWKI');
            }))
            ->willReturnSelf();

        $sut = $this->makeSut($http);
        $sut->fetchOrThrow('https://example.com/');
    }

    // =========================================================================
    // fetchOrThrow — failure
    // =========================================================================

    public function testItFetchOrThrowThrowsOnUnsuccessfulResponse(): void
    {
        $http = $this->makePendingRequest();
        $http->method('getSsrfSafe')->willReturn($this->makeResponse(false));

        $sut = $this->makeSut($http);

        static::expectException(FailedToFetchUrlException::class);
        static::expectExceptionMessage('Failed to fetch external URL "https://example.com/".');

        $sut->fetchOrThrow('https://example.com/');
    }
}
