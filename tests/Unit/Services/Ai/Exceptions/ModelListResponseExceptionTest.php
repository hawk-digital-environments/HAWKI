<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Exceptions;

use App\Services\Ai\Exceptions\ModelListResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelListResponseException::class)]
class ModelListResponseExceptionTest extends TestCase
{
    // =========================================================================
    // forInvalidJson
    // =========================================================================

    public function testItForInvalidJsonReturnsCorrectExceptionType(): void
    {
        $previous = new \RuntimeException('Syntax error');
        $sut = ModelListResponseException::forInvalidJson('not-json', $previous);
        static::assertInstanceOf(ModelListResponseException::class, $sut);
        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    public function testItForInvalidJsonIncludesBodyInMessage(): void
    {
        $previous = new \RuntimeException('Syntax error');
        $sut = ModelListResponseException::forInvalidJson('{"broken":', $previous);
        static::assertStringContainsString('{"broken":', $sut->getMessage());
    }

    public function testItForInvalidJsonChainsPreviousException(): void
    {
        $previous = new \RuntimeException('Syntax error');
        $sut = ModelListResponseException::forInvalidJson('bad', $previous);
        static::assertSame($previous, $sut->getPrevious());
    }

    public function testItForInvalidJsonMessageMatchesExpectedFormat(): void
    {
        $previous = new \RuntimeException('err');
        $sut = ModelListResponseException::forInvalidJson('raw body', $previous);
        static::assertSame(
            'Failed to parse model list response as JSON: raw body',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forNonArrayResponse
    // =========================================================================

    public function testItForNonArrayResponseReturnsCorrectExceptionType(): void
    {
        $sut = ModelListResponseException::forNonArrayResponse('string');
        static::assertInstanceOf(ModelListResponseException::class, $sut);
    }

    public function testItForNonArrayResponseIncludesTypeInMessage(): void
    {
        $sut = ModelListResponseException::forNonArrayResponse('string');
        static::assertStringContainsString('string', $sut->getMessage());
    }

    public function testItForNonArrayResponseMessageMatchesExpectedFormat(): void
    {
        $sut = ModelListResponseException::forNonArrayResponse('integer');
        static::assertSame(
            'Model list response is not an array, got integer.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forNonArrayExtract
    // =========================================================================

    public function testItForNonArrayExtractReturnsCorrectExceptionType(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data', 'some-string');
        static::assertInstanceOf(ModelListResponseException::class, $sut);
    }

    public function testItForNonArrayExtractIncludesPathInMessage(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data.models', null);
        static::assertStringContainsString('data.models', $sut->getMessage());
    }

    public function testItForNonArrayExtractJsonEncodesTheValue(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data', 'hello');
        static::assertStringContainsString('"hello"', $sut->getMessage());
    }

    public function testItForNonArrayExtractEncodesNullValue(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data', null);
        static::assertStringContainsString('null', $sut->getMessage());
    }

    public function testItForNonArrayExtractMessageMatchesExpectedFormat(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data', 42);
        static::assertSame(
            'Extracted data at path "data" is not an array, got: 42',
            $sut->getMessage()
        );
    }
}
