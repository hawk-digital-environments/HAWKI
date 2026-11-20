<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Values;

use App\Services\Ai\Values\StreamErrorCode;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StreamErrorCode::class)]
class StreamErrorCodeTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function requestException(int $status): RequestException
    {
        return new RequestException(new Response(new PsrResponse($status)));
    }

    // =========================================================================
    // fromThrowable: direct causes
    // =========================================================================

    public function testItMapsRateLimitedToProviderBusy(): void
    {
        $sut = StreamErrorCode::fromThrowable(RateLimitedException::forProvider('openai'));

        static::assertSame(StreamErrorCode::PROVIDER_BUSY, $sut);
    }

    public function testItMapsProviderOverloadedToProviderBusy(): void
    {
        $sut = StreamErrorCode::fromThrowable(ProviderOverloadedException::forProvider('openai'));

        static::assertSame(StreamErrorCode::PROVIDER_BUSY, $sut);
    }

    public function testItMapsConnectionExceptionToProviderUnreachable(): void
    {
        $sut = StreamErrorCode::fromThrowable(new ConnectionException('cURL error 28'));

        static::assertSame(StreamErrorCode::PROVIDER_UNREACHABLE, $sut);
    }

    public function testItMapsTooManyRequestsResponseToProviderBusy(): void
    {
        $sut = StreamErrorCode::fromThrowable($this->requestException(429));

        static::assertSame(StreamErrorCode::PROVIDER_BUSY, $sut);
    }

    public function testItMapsServerErrorResponseToProviderUnreachable(): void
    {
        $sut = StreamErrorCode::fromThrowable($this->requestException(503));

        static::assertSame(StreamErrorCode::PROVIDER_UNREACHABLE, $sut);
    }

    public function testItMapsClientErrorResponseResponseToGenerationFailed(): void
    {
        $sut = StreamErrorCode::fromThrowable($this->requestException(400));

        static::assertSame(StreamErrorCode::GENERATION_FAILED, $sut);
    }

    public function testItMapsUnknownThrowableToGenerationFailed(): void
    {
        $sut = StreamErrorCode::fromThrowable(new RuntimeException('nope'));

        static::assertSame(StreamErrorCode::GENERATION_FAILED, $sut);
    }

    // =========================================================================
    // fromThrowable: wrapped causes
    // =========================================================================

    public function testItClassifiesWrappedRateLimitByUnderlyingCause(): void
    {
        $e = new RuntimeException('wrapped', previous: RateLimitedException::forProvider('openai'));

        $sut = StreamErrorCode::fromThrowable($e);

        static::assertSame(StreamErrorCode::PROVIDER_BUSY, $sut);
    }

    public function testItClassifiesWrappedConnectionErrorByUnderlyingCause(): void
    {
        $e = new RuntimeException('wrapped', previous: new ConnectionException('cURL error 7'));

        $sut = StreamErrorCode::fromThrowable($e);

        static::assertSame(StreamErrorCode::PROVIDER_UNREACHABLE, $sut);
    }

    public function testItClassifiesByFirstRecognizableCauseInTheChain(): void
    {
        // The outer ConnectionException wins over the deeper RateLimitedException,
        // matching the previous streamErrorCode() implementation.
        $e = new ConnectionException('outer', 0, RateLimitedException::forProvider('openai'));

        $sut = StreamErrorCode::fromThrowable($e);

        static::assertSame(StreamErrorCode::PROVIDER_UNREACHABLE, $sut);
    }

    // =========================================================================
    // Wire values
    // =========================================================================

    public function testItSerializesToTheStableClientFacingCodes(): void
    {
        static::assertSame('PROVIDER_BUSY', StreamErrorCode::PROVIDER_BUSY->value);
        static::assertSame('PROVIDER_UNREACHABLE', StreamErrorCode::PROVIDER_UNREACHABLE->value);
        static::assertSame('GENERATION_FAILED', StreamErrorCode::GENERATION_FAILED->value);
    }
}
