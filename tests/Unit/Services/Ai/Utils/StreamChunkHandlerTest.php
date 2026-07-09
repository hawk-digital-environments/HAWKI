<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Utils;

use App\Services\Ai\Utils\StreamChunkHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(StreamChunkHandler::class)]
class StreamChunkHandlerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Creates a StreamChunkHandler that appends each received JSON string to $received.
     *
     * @param array<string> $received Reference to the array that will collect all callback invocations.
     */
    private function makeHandler(array &$received): StreamChunkHandler
    {
        return new StreamChunkHandler(function (string $chunk) use (&$received) {
            $received[] = $chunk;
        });
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);
        static::assertInstanceOf(StreamChunkHandler::class, $sut);
    }

    // =========================================================================
    // Standard SSE format (data: {...})
    // =========================================================================

    public function testItInvokesCallbackForSingleSseChunk(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        $json = '{"id":"1","delta":"hello"}';
        $sut->handle('data: ' . $json . "\n");

        static::assertCount(1, $received);
        static::assertSame($json . "\n", $received[0]);
    }

    public function testItInvokesCallbackForEachSseChunkInMultiDataLine(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        $json1 = '{"delta":"foo"}';
        $json2 = '{"delta":"bar"}';
        $sut->handle('data: ' . $json1 . "\ndata: " . $json2 . "\n");

        static::assertCount(2, $received);
    }

    public function testItSkipsEmptySseSegments(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        // Leading empty segment before the first "data: " token
        $sut->handle('data: {"ok":true}');

        static::assertCount(1, $received);
    }

    public function testItSkipsNonJsonSseSegments(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        // "[DONE]" is a common non-JSON SSE sentinel
        $sut->handle("data: [DONE]\n");

        static::assertCount(0, $received);
    }

    // =========================================================================
    // Google / raw JSON array format
    // =========================================================================

    public function testItHandlesSingleCompleteJsonObjectWithoutSsePrefix(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        $json = '{"candidates":[{"content":"hi"}]}';
        $sut->handle($json);

        static::assertCount(1, $received);
        static::assertStringContainsString('"candidates"', $received[0]);
    }

    public function testItHandlesJsonObjectSplitAcrossMultipleCalls(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        $json = '{"candidates":[{"content":"hello"}]}';
        $half = intdiv(strlen($json), 2);

        $sut->handle(substr($json, 0, $half));
        static::assertCount(0, $received, 'No callback until the object is complete');

        $sut->handle(substr($json, $half));
        static::assertCount(1, $received);
    }

    public function testItHandlesMultipleJsonObjectsInOneRawChunk(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        $json1 = '{"n":1}';
        $json2 = '{"n":2}';
        $sut->handle($json1 . ',' . $json2);

        static::assertCount(2, $received);
    }

    public function testItResetBufferWhenClosingBracketReceived(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        // Simulate the Google format wrapping array end marker
        $sut->handle('{"n":1}');
        $sut->handle(']');
        // After ']' the buffer is reset; subsequent objects should work fine
        $sut->handle('{"n":2}');

        static::assertCount(2, $received);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testItIgnoresCompletelyEmptyInput(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        $sut->handle('');

        static::assertCount(0, $received);
    }

    public function testItIgnoresWhitespaceOnlyInput(): void
    {
        $received = [];
        $sut = $this->makeHandler($received);

        $sut->handle("   \n  ");

        static::assertCount(0, $received);
    }
}
