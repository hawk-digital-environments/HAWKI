<?php
declare(strict_types=1);

namespace Tests\Unit\Services\FileConverter\Exception;

use App\Services\FileConverter\Exception\ConversionFailedException;
use App\Services\FileConverter\Exception\FileConverterExceptionInterface;
use App\Services\FileConverter\Interfaces\FileConverterInterface;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConversionFailedException::class)]
class ConversionFailedExceptionTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeConverter(): FileConverterInterface
    {
        return $this->createMock(FileConverterInterface::class);
    }

    private function makeResponse(int $status, string $body): Response
    {
        $mock = $this->createMock(Response::class);
        $mock->method('status')->willReturn($status);
        $mock->method('body')->willReturn($body);
        return $mock;
    }

    // =========================================================================
    // Interface contract
    // =========================================================================

    public function testItImplementsFileConverterExceptionInterface(): void
    {
        $sut = ConversionFailedException::forString($this->makeConverter(), 'msg');
        static::assertInstanceOf(FileConverterExceptionInterface::class, $sut);
    }

    public function testItExtendsRuntimeException(): void
    {
        $sut = ConversionFailedException::forString($this->makeConverter(), 'msg');
        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    // =========================================================================
    // forThrowable
    // =========================================================================

    public function testItForThrowableIncludesConverterClassName(): void
    {
        $converter = $this->makeConverter();
        $inner = new \RuntimeException('inner error');

        $sut = ConversionFailedException::forThrowable($converter, $inner);

        static::assertStringContainsString(get_class($converter), $sut->getMessage());
    }

    public function testItForThrowableIncludesInnerExceptionMessage(): void
    {
        $sut = ConversionFailedException::forThrowable(
            $this->makeConverter(),
            new \RuntimeException('inner error')
        );

        static::assertStringContainsString('inner error', $sut->getMessage());
    }

    public function testItForThrowablePreservesInnerExceptionAsPrevious(): void
    {
        $inner = new \RuntimeException('inner error');

        $sut = ConversionFailedException::forThrowable($this->makeConverter(), $inner);

        static::assertSame($inner, $sut->getPrevious());
    }

    // =========================================================================
    // forFailedResponse
    // =========================================================================

    public function testItForFailedResponseIncludesConverterClassName(): void
    {
        $converter = $this->makeConverter();
        $response = $this->makeResponse(422, 'Unprocessable');

        $sut = ConversionFailedException::forFailedResponse($converter, $response);

        static::assertStringContainsString(get_class($converter), $sut->getMessage());
    }

    public function testItForFailedResponseIncludesStatusCode(): void
    {
        $sut = ConversionFailedException::forFailedResponse(
            $this->makeConverter(),
            $this->makeResponse(503, 'Service Unavailable')
        );

        static::assertStringContainsString('503', $sut->getMessage());
    }

    public function testItForFailedResponseIncludesResponseBody(): void
    {
        $sut = ConversionFailedException::forFailedResponse(
            $this->makeConverter(),
            $this->makeResponse(500, 'Internal Server Error body')
        );

        static::assertStringContainsString('Internal Server Error body', $sut->getMessage());
    }

    // =========================================================================
    // forString
    // =========================================================================

    public function testItForStringIncludesConverterClassName(): void
    {
        $converter = $this->makeConverter();

        $sut = ConversionFailedException::forString($converter, 'disk full');

        static::assertStringContainsString(get_class($converter), $sut->getMessage());
    }

    public function testItForStringIncludesProvidedMessage(): void
    {
        $sut = ConversionFailedException::forString($this->makeConverter(), 'disk full');

        static::assertStringContainsString('disk full', $sut->getMessage());
    }
}
