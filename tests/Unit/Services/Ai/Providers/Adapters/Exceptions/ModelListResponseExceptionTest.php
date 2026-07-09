<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Providers\Adapters\Exceptions;

use App\Services\Ai\Exceptions\ModelListResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelListResponseException::class)]
class ModelListResponseExceptionTest extends TestCase
{
    // =========================================================================
    // forInvalidJson
    // =========================================================================

    public function testItCreatesExceptionForInvalidJson(): void
    {
        $previous = new \JsonException('Syntax error');
        $sut = ModelListResponseException::forInvalidJson('not-json', $previous);
        static::assertInstanceOf(ModelListResponseException::class, $sut);
    }

    public function testItIsARuntimeException(): void
    {
        $sut = ModelListResponseException::forInvalidJson('x', new \JsonException('e'));
        static::assertInstanceOf(\RuntimeException::class, $sut);
    }

    public function testItIncludesBodyInInvalidJsonMessage(): void
    {
        $sut = ModelListResponseException::forInvalidJson('not-json-body', new \JsonException('e'));
        static::assertStringContainsString('not-json-body', $sut->getMessage());
    }

    public function testItChainsPreviousExceptionForInvalidJson(): void
    {
        $previous = new \JsonException('Syntax error');
        $sut = ModelListResponseException::forInvalidJson('body', $previous);
        static::assertSame($previous, $sut->getPrevious());
    }

    public function testItMatchesExpectedMessageFormatForInvalidJson(): void
    {
        $body = 'not valid json at all';
        $previous = new \JsonException('Syntax error');
        $sut = ModelListResponseException::forInvalidJson($body, $previous);
        static::assertStringContainsString(
            sprintf('Failed to parse model list response as JSON: %s', $body),
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forNonArrayResponse
    // =========================================================================

    public function testItCreatesExceptionForNonArrayResponse(): void
    {
        $sut = ModelListResponseException::forNonArrayResponse('string');
        static::assertInstanceOf(ModelListResponseException::class, $sut);
    }

    public function testItIncludesTypeInNonArrayResponseMessage(): void
    {
        $sut = ModelListResponseException::forNonArrayResponse('int');
        static::assertStringContainsString('int', $sut->getMessage());
    }

    public function testItMatchesExpectedMessageFormatForNonArrayResponse(): void
    {
        $type = 'string';
        $sut = ModelListResponseException::forNonArrayResponse($type);
        static::assertStringContainsString(
            sprintf('Model list response is not an array, got %s.', $type),
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forNonArrayExtract
    // =========================================================================

    public function testItCreatesExceptionForNonArrayExtract(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data.*', 'unexpected string');
        static::assertInstanceOf(ModelListResponseException::class, $sut);
    }

    public function testItIncludesPathInNonArrayExtractMessage(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data.models', null);
        static::assertStringContainsString('data.models', $sut->getMessage());
    }

    public function testItJsonEncodesValueInNonArrayExtractMessage(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data.*', 'some string');
        static::assertStringContainsString('"some string"', $sut->getMessage());
    }

    public function testItHandlesNullValueInNonArrayExtractMessage(): void
    {
        $sut = ModelListResponseException::forNonArrayExtract('data.*', null);
        static::assertStringContainsString('null', $sut->getMessage());
    }

    public function testItMatchesExpectedMessageFormatForNonArrayExtract(): void
    {
        $path = 'data.*';
        $value = 'unexpected';
        $sut = ModelListResponseException::forNonArrayExtract($path, $value);
        static::assertStringContainsString(
            sprintf('Extracted data at path "%s" is not an array, got: %s', $path, json_encode($value)),
            $sut->getMessage()
        );
    }
}
