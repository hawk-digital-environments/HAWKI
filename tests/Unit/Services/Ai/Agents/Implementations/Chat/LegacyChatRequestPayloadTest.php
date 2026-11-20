<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Implementations\Chat;

use App\Services\Ai\Agents\Implementations\Chat\LegacyChatRequestPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(LegacyChatRequestPayload::class)]
class LegacyChatRequestPayloadTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @param list<array<string, mixed>> $messages
     */
    private function makeRequest(array $messages, array $extraPayload = []): array
    {
        return [
            'payload' => [
                'model'    => 'gpt-4o',
                'messages' => $messages,
                ...$extraPayload
            ],
        ];
    }

    // =========================================================================
    // tryFromRequest
    // =========================================================================

    public function testItAcceptsAValidLegacyRequest(): void
    {
        $sut = LegacyChatRequestPayload::tryFromRequest($this->makeRequest([
            ['role' => 'system', 'content' => ['text' => 'Be helpful.']],
            ['role' => 'user', 'content' => ['text' => 'Hello!']],
        ]));

        static::assertNotNull($sut);
    }

    public static function provideInvalidRequests(): iterable
    {
        yield 'not an array' => ['some string'];
        yield 'missing payload key' => [[]];
        yield 'payload not an array' => [['payload' => 'nope']];
        yield 'missing messages' => [['payload' => ['model' => 'gpt-4o']]];
        yield 'messages not an array' => [['payload' => ['model' => 'gpt-4o', 'messages' => 'nope']]];
        yield 'missing model' => [['payload' => ['messages' => []]]];
        yield 'model not a string' => [['payload' => ['model' => 42, 'messages' => []]]];
    }

    #[DataProvider('provideInvalidRequests')]
    public function testItReturnsNullForNonMatchingRequests(mixed $request): void
    {
        static::assertNull(LegacyChatRequestPayload::tryFromRequest($request));
    }

    // =========================================================================
    // systemInstructions
    // =========================================================================

    public function testItExtractsTheFirstSystemMessageText(): void
    {
        $sut = LegacyChatRequestPayload::tryFromRequest($this->makeRequest([
            ['role' => 'system', 'content' => ['text' => 'Be helpful.']],
            ['role' => 'system', 'content' => ['text' => 'Ignored.']],
            ['role' => 'user', 'content' => ['text' => 'Hello!']],
        ]));

        static::assertSame('Be helpful.', $sut?->systemInstructions());
    }

    public function testItReturnsEmptyInstructionsWhenNoSystemMessageIsPresent(): void
    {
        $sut = LegacyChatRequestPayload::tryFromRequest($this->makeRequest([
            ['role' => 'user', 'content' => ['text' => 'Hello!']],
        ]));

        static::assertSame('', $sut?->systemInstructions());
    }

    public function testItReturnsEmptyInstructionsForEmptySystemMessageText(): void
    {
        $sut = LegacyChatRequestPayload::tryFromRequest($this->makeRequest([
            ['role' => 'system', 'content' => ['text' => '']],
            ['role' => 'user', 'content' => ['text' => 'Hello!']],
        ]));

        static::assertSame('', $sut?->systemInstructions());
    }

    public function testItReturnsEmptyInstructionsForNullSystemMessageText(): void
    {
        $sut = LegacyChatRequestPayload::tryFromRequest($this->makeRequest([
            ['role' => 'system', 'content' => ['text' => null]],
            ['role' => 'user', 'content' => ['text' => 'Hello!']],
        ]));

        static::assertSame('', $sut?->systemInstructions());
    }

    public function testItReturnsEmptyInstructionsWhenSystemMessageTextIsAbsent(): void
    {
        $sut = LegacyChatRequestPayload::tryFromRequest($this->makeRequest([
            ['role' => 'system', 'content' => ['attachments' => []]],
            ['role' => 'user', 'content' => ['text' => 'Hello!']],
        ]));

        static::assertSame('', $sut?->systemInstructions());
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function testItExposesModelIdMessagesParamsToolsAndBroadcastFlag(): void
    {
        $sut = LegacyChatRequestPayload::tryFromRequest($this->makeRequest(
            [['role' => 'user', 'content' => ['text' => 'Hello!']]],
            [
                'params'    => ['temp' => 0.7],
                'tools'     => ['capability:web_search:auto'],
                'broadcast' => true,
            ]
        ));

        static::assertNotNull($sut);
        static::assertSame('gpt-4o', $sut->modelId());
        static::assertSame([['role' => 'user', 'content' => ['text' => 'Hello!']]], $sut->messages());
        static::assertSame(['temp' => 0.7], $sut->params());
        static::assertSame(['capability:web_search:auto'], $sut->tools());
        static::assertTrue($sut->isBroadcast());
    }

    public function testItDefaultsParamsToolsAndBroadcastWhenAbsent(): void
    {
        $sut = LegacyChatRequestPayload::tryFromRequest($this->makeRequest(
            [['role' => 'user', 'content' => ['text' => 'Hello!']]]
        ));

        static::assertNotNull($sut);
        static::assertSame([], $sut->params());
        static::assertSame([], $sut->tools());
        static::assertFalse($sut->isBroadcast());
    }

    public function testItReturnsTheRawPayloadArray(): void
    {
        $request = $this->makeRequest([['role' => 'user', 'content' => ['text' => 'Hello!']]]);
        $sut = LegacyChatRequestPayload::tryFromRequest($request);

        static::assertSame($request['payload'], $sut?->toArray());
    }
}
