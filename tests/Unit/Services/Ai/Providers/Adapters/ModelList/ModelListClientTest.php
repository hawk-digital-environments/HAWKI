<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Providers\Adapters\ModelList;

use App\Services\Ai\Exceptions\ModelListRequestException;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListClient;
use App\Services\Ai\Providers\Adapters\ModelList\ModelListResponse;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelListClient::class)]
class ModelListClientTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new ModelListClient(fn() => $this->makeSuccessRequest([]));
        static::assertInstanceOf(ModelListClient::class, $sut);
    }

    // =========================================================================
    // get
    // =========================================================================

    public function testItGetReturnsModelListResponse(): void
    {
        $sut = new ModelListClient(fn() => $this->makeSuccessRequest(['data' => []]));
        static::assertInstanceOf(ModelListResponse::class, $sut->get('/models'));
    }

    public function testItGetPassesUrlToRequest(): void
    {
        $capturedUrl = null;
        $pendingRequest = $this->createMock(PendingRequest::class);
        $pendingRequest->expects($this->once())
            ->method('get')
            ->with('/v1/models')
            ->willReturn(new Response(new PsrResponse(200, [], '{"data":[]}')));

        $sut = new ModelListClient(fn() => $pendingRequest);
        $sut->get('/v1/models');
    }

    public function testItGetCallsRequestFactoryOnEachCall(): void
    {
        $callCount = 0;
        $factory = function () use (&$callCount): PendingRequest {
            $callCount++;
            return $this->makeSuccessRequest([]);
        };

        $sut = new ModelListClient($factory);
        $sut->get('/models');
        $sut->get('/models');

        static::assertSame(2, $callCount);
    }

    public function testItGetThrowsModelListRequestExceptionOnConnectionFailure(): void
    {
        $sut = new ModelListClient(function (): PendingRequest {
            $pendingRequest = $this->createMock(PendingRequest::class);
            $pendingRequest->method('get')
                ->willThrowException(new ConnectionException('Connection refused'));
            return $pendingRequest;
        });

        $this->expectException(ModelListRequestException::class);
        $this->expectExceptionMessage('Failed to connect to model list endpoint "/models"');
        $sut->get('/models');
    }

    public function testItGetThrowsModelListRequestExceptionOnNon2xxResponse(): void
    {
        $sut = new ModelListClient(function (): PendingRequest {
            $pendingRequest = $this->createMock(PendingRequest::class);
            $pendingRequest->method('get')
                ->willReturn(new Response(new PsrResponse(401, [], '{"error":"Unauthorized"}')));
            return $pendingRequest;
        });

        $this->expectException(ModelListRequestException::class);
        $this->expectExceptionMessage('Model list request to "/models" returned a non-successful response');
        $sut->get('/models');
    }

    public function testItGetIncludesResponseBodyInExceptionMessageOnFailure(): void
    {
        $body = '{"error":"rate_limit_exceeded"}';
        $sut = new ModelListClient(function () use ($body): PendingRequest {
            $pendingRequest = $this->createMock(PendingRequest::class);
            $pendingRequest->method('get')
                ->willReturn(new Response(new PsrResponse(429, [], $body)));
            return $pendingRequest;
        });

        $this->expectException(ModelListRequestException::class);
        $this->expectExceptionMessage($body);
        $sut->get('/models');
    }

    public function testItGetWrapsConnectionExceptionAsPreviousException(): void
    {
        $connectionException = new ConnectionException('ECONNREFUSED');
        $sut = new ModelListClient(function () use ($connectionException): PendingRequest {
            $pendingRequest = $this->createMock(PendingRequest::class);
            $pendingRequest->method('get')->willThrowException($connectionException);
            return $pendingRequest;
        });

        try {
            $sut->get('/models');
            static::fail('Expected ModelListRequestException was not thrown');
        } catch (ModelListRequestException $e) {
            static::assertSame($connectionException, $e->getPrevious());
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSuccessRequest(array $data): PendingRequest
    {
        $body = (string) json_encode($data);
        $pendingRequest = $this->createMock(PendingRequest::class);
        $pendingRequest->method('get')
            ->willReturn(new Response(new PsrResponse(200, [], $body)));
        return $pendingRequest;
    }
}
