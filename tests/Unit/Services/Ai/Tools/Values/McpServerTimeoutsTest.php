<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Tools\Values;

use App\Services\Ai\Tools\Values\McpServerTimeouts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(McpServerTimeouts::class)]
class McpServerTimeoutsTest extends TestCase
{
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new McpServerTimeouts(readTimeout: 10.0, connectionTimeout: 5.0, sseIdleTimeout: 30.0);
        static::assertInstanceOf(McpServerTimeouts::class, $sut);
    }

    // =========================================================================
    // fromArray
    // =========================================================================

    public function testItCreatesFromArrayWithAllKeys(): void
    {
        $sut = McpServerTimeouts::fromArray(['read' => 10, 'connect' => 5, 'sse_idle' => 30]);

        static::assertSame(10.0, $sut->readTimeout);
        static::assertSame(5.0, $sut->connectionTimeout);
        static::assertSame(30.0, $sut->sseIdleTimeout);
    }

    public function testItCreatesFromArrayWithMissingKeysAsNull(): void
    {
        $sut = McpServerTimeouts::fromArray([]);

        static::assertNull($sut->readTimeout);
        static::assertNull($sut->connectionTimeout);
        static::assertNull($sut->sseIdleTimeout);
    }

    public function testItCreatesFromArrayWithPartialKeys(): void
    {
        $sut = McpServerTimeouts::fromArray(['read' => 15.5]);

        static::assertSame(15.5, $sut->readTimeout);
        static::assertNull($sut->connectionTimeout);
        static::assertNull($sut->sseIdleTimeout);
    }

    public function testItCastsFromArrayValuesToFloat(): void
    {
        $sut = McpServerTimeouts::fromArray(['read' => '10', 'connect' => '5']);

        static::assertSame(10.0, $sut->readTimeout);
        static::assertSame(5.0, $sut->connectionTimeout);
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testItSerializesToArrayOmittingNullValues(): void
    {
        $sut = new McpServerTimeouts(readTimeout: 10.0);

        static::assertSame(['read' => 10.0], $sut->toArray());
    }

    public function testItSerializesToEmptyArrayWhenAllNull(): void
    {
        $sut = new McpServerTimeouts();

        static::assertSame([], $sut->toArray());
    }

    public function testItSerializesToArrayWithAllKeys(): void
    {
        $sut = new McpServerTimeouts(readTimeout: 10.0, connectionTimeout: 5.0, sseIdleTimeout: 30.0);

        static::assertSame(
            ['read' => 10.0, 'connect' => 5.0, 'sse_idle' => 30.0],
            $sut->toArray()
        );
    }

    // =========================================================================
    // jsonSerialize
    // =========================================================================

    public function testItJsonSerializesTheSameAsToArray(): void
    {
        $sut = new McpServerTimeouts(readTimeout: 10.0, connectionTimeout: 5.0);

        static::assertSame($sut->toArray(), $sut->jsonSerialize());
    }

    public function testItRoundTripsViaFromArrayAndToArray(): void
    {
        $original = new McpServerTimeouts(readTimeout: 10.0, connectionTimeout: 5.0, sseIdleTimeout: 30.0);
        $restored = McpServerTimeouts::fromArray($original->toArray());

        static::assertSame($original->readTimeout, $restored->readTimeout);
        static::assertSame($original->connectionTimeout, $restored->connectionTimeout);
        static::assertSame($original->sseIdleTimeout, $restored->sseIdleTimeout);
    }
}
