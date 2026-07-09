<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\JsonApi;

use App\Services\System\JsonApi\ValueSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(ValueSerializer::class)]
class ValueSerializerTest extends TestCase
{
    // =========================================================================
    // apiKey
    // =========================================================================

    public function testItApiKeyReturnsNullForNull(): void
    {
        static::assertNull(ValueSerializer::apiKey(null));
    }

    public function testItApiKeyReturnsNullForEmptyString(): void
    {
        static::assertNull(ValueSerializer::apiKey(''));
    }

    public function testItApiKeyMasksKeyShowingLastFourChars(): void
    {
        $result = ValueSerializer::apiKey('abcdefghij');

        static::assertSame('******ghij', $result);
    }

    public function testItApiKeyMasksKeyUpToEightCharsCompletely(): void
    {
        $result = ValueSerializer::apiKey('12345678');

        static::assertSame('********', $result);
    }

    public function testItApiKeyMasksShortKeyCompletely(): void
    {
        $result = ValueSerializer::apiKey('abc');

        static::assertSame('***', $result);
    }

    public function testItApiKeyMasksNineCharKeyShowingLastFour(): void
    {
        $result = ValueSerializer::apiKey('123456789');

        static::assertSame('*****6789', $result);
    }

    // =========================================================================
    // localFileAsDataUrl
    // =========================================================================

    public function testItLocalFileAsDataUrlReturnsNullForNull(): void
    {
        static::assertNull(ValueSerializer::localFileAsDataUrl(null));
    }

    public function testItLocalFileAsDataUrlReturnsNullForEmptyString(): void
    {
        static::assertNull(ValueSerializer::localFileAsDataUrl(''));
    }

    public function testItLocalFileAsDataUrlReturnsNullForNonExistentFile(): void
    {
        static::assertNull(ValueSerializer::localFileAsDataUrl('/nonexistent/path/file.png'));
    }

    public function testItLocalFileAsDataUrlReturnsDataUrlForExistingFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'value_serializer_test_');
        file_put_contents($tempFile, 'hello');

        try {
            $result = ValueSerializer::localFileAsDataUrl($tempFile);
            static::assertNotNull($result);
            static::assertStringStartsWith('data:', $result);
            static::assertStringContainsString(';base64,', $result);
            static::assertStringContainsString(base64_encode('hello'), $result);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testItLocalFileAsDataUrlEmbedsMimeType(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'value_serializer_test_') . '.txt';
        file_put_contents($tempFile, 'plain text content');

        try {
            $result = ValueSerializer::localFileAsDataUrl($tempFile);
            static::assertNotNull($result);
            static::assertStringStartsWith('data:text/', $result);
        } finally {
            @unlink($tempFile);
        }
    }
}
