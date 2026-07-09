<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Providers\Adapters\Exceptions;

use App\Services\Ai\Exceptions\ModelListRequestException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelListRequestException::class)]
class ModelListRequestExceptionTest extends TestCase
{
    // =========================================================================
    // forConnectionFailure
    // =========================================================================

    public function testItCreatesExceptionForConnectionFailure(): void
    {
        $previous = new \RuntimeException('Connection timed out');
        $sut = ModelListRequestException::forConnectionFailure('/models', $previous);
        static::assertInstanceOf(ModelListRequestException::class, $sut);
    }

    public function testItIsARuntimeException(): void
    {
        $sut = ModelListRequestException::forConnectionFailure('/models', new \RuntimeException('err'));
        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    public function testItIncludesUrlInConnectionFailureMessage(): void
    {
        $sut = ModelListRequestException::forConnectionFailure('/models', new \RuntimeException('err'));
        static::assertStringContainsString('/models', $sut->getMessage());
    }

    public function testItIncludesPreviousMessageInConnectionFailureMessage(): void
    {
        $previous = new \RuntimeException('Connection timed out');
        $sut = ModelListRequestException::forConnectionFailure('/models', $previous);
        static::assertStringContainsString('Connection timed out', $sut->getMessage());
    }

    public function testItChainsPreviousExceptionForConnectionFailure(): void
    {
        $previous = new \RuntimeException('original cause');
        $sut = ModelListRequestException::forConnectionFailure('/models', $previous);
        static::assertSame($previous, $sut->getPrevious());
    }

    public function testItMatchesExpectedMessageFormatForConnectionFailure(): void
    {
        $url = '/v1/models';
        $previous = new \RuntimeException('ETIMEDOUT');
        $sut = ModelListRequestException::forConnectionFailure($url, $previous);
        static::assertStringContainsString(
            sprintf('Failed to connect to model list endpoint "%s": %s', $url, $previous->getMessage()),
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forUnsuccessfulResponse
    // =========================================================================

    public function testItCreatesExceptionForUnsuccessfulResponse(): void
    {
        $sut = ModelListRequestException::forUnsuccessfulResponse('/models', '{"error":"Unauthorized"}');
        static::assertInstanceOf(ModelListRequestException::class, $sut);
    }

    public function testItIncludesUrlInUnsuccessfulResponseMessage(): void
    {
        $sut = ModelListRequestException::forUnsuccessfulResponse('/models', 'body');
        static::assertStringContainsString('/models', $sut->getMessage());
    }

    public function testItIncludesBodyInUnsuccessfulResponseMessage(): void
    {
        $sut = ModelListRequestException::forUnsuccessfulResponse('/models', '{"error":"Unauthorized"}');
        static::assertStringContainsString('{"error":"Unauthorized"}', $sut->getMessage());
    }

    public function testItMatchesExpectedMessageFormatForUnsuccessfulResponse(): void
    {
        $url = '/v1/models';
        $body = '{"error":"rate_limit_exceeded"}';
        $sut = ModelListRequestException::forUnsuccessfulResponse($url, $body);
        static::assertStringContainsString(
            sprintf('Model list request to "%s" returned a non-successful response: %s', $url, $body),
            $sut->getMessage()
        );
    }

    public function testItHasNoPreviousExceptionForUnsuccessfulResponse(): void
    {
        $sut = ModelListRequestException::forUnsuccessfulResponse('/models', 'error body');
        static::assertNull($sut->getPrevious());
    }
}
