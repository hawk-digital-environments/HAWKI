<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Implementations\Legacy;

use App\Services\Ai\Chat\Values\CitationData;
use App\Services\Ai\Chat\Values\Stream\ContentBlockStartEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiCitationEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiProviderToolEvent;
use App\Services\Ai\Chat\Values\Stream\ReasoningDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\StreamEndEvent;
use App\Services\Ai\Chat\Values\Stream\StreamStartEvent;
use App\Services\Ai\Chat\Values\Stream\TextDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallStartEvent;
use App\Services\Ai\Formatters\Implementations\Legacy\LegacyStreamContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LegacyStreamContext::class)]
class LegacyStreamContextTest extends TestCase
{
    private LegacyStreamContext $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new LegacyStreamContext(author: [
            'username' => 'HAWKI',
            'name' => 'HAWKI Bot',
            'avatar_url' => null,
        ]);
    }

    public function testItEmitsTheHeaderFrameOnStreamStart(): void
    {
        $frames = $this->sut->transform(new StreamStartEvent('s1', 'gpt-4.1-nano'));

        self::assertSame('header', $frames[0]['type']);
        self::assertFalse($frames[0]['isDone']);
        self::assertSame('gpt-4.1-nano', $frames[0]['model']);
        self::assertSame('HAWKI', $frames[0]['author']['username']);
        self::assertSame([], $frames[0]['tools']);
    }

    public function testItStreamsMessageDeltasAndAccumulatesTheText(): void
    {
        $this->sut->transform(new StreamStartEvent('s1', 'm'));

        $first = $this->sut->transform(new TextDeltaEvent('Hel'));
        $second = $this->sut->transform(new TextDeltaEvent('lo'));

        self::assertSame('message', $first[0]['type']);
        self::assertSame('Hel', $first[0]['content']);
        self::assertSame('lo', $second[0]['content']);

        $frames = $this->sut->end();
        $completion = $frames[array_key_last($frames)];

        self::assertSame('completion', $completion['type']);
        self::assertTrue($completion['isDone']);
        self::assertSame('Hello', $completion['content']);
    }

    public function testItMapsProgressEventsToStatusFrames(): void
    {
        $this->sut->transform(new StreamStartEvent('s1', 'm'));

        $reasoning = $this->sut->transform(new ContentBlockStartEvent(0, ContentBlockStartEvent::BLOCK_TYPE_THINKING));
        self::assertSame('status', $reasoning[0]['type']);
        self::assertSame(['key' => 'reasoning', 'value' => ''], $reasoning[0]['status']);

        $delta = $this->sut->transform(new ReasoningDeltaEvent('thinking...'));
        self::assertSame(['key' => 'reasoning_delta', 'value' => 'thinking...'], $delta[0]['status']);

        $providerTool = $this->sut->transform(new HawkiProviderToolEvent('ws_1', 'web_search_call', [], 'completed'));
        self::assertSame(['key' => 'provider_tool_call', 'value' => 'web_search_call'], $providerTool[0]['status']);

        $toolCall = $this->sut->transform(new ToolCallStartEvent('call_1', 'read_file'));
        self::assertSame(['key' => 'tool_call', 'value' => 'read_file'], $toolCall[0]['status']);
    }

    public function testItBatchesCitationsUntilTheEnd(): void
    {
        $this->sut->transform(new StreamStartEvent('s1', 'm'));
        $this->sut->transform(new TextDeltaEvent('cite me'));
        self::assertSame([], $this->sut->transform(new HawkiCitationEvent(new CitationData(
            url: 'https://example.com',
            title: 'Example',
            startIndex: 0,
            endIndex: 3,
        ))));

        $frames = $this->sut->end();

        self::assertSame('citation', $frames[0]['type']);
        self::assertSame([
            'url' => 'https://example.com',
            'title' => 'Example',
            'start_index' => 0,
            'end_index' => 3,
        ], $frames[0]['content']);
        self::assertSame('message', $frames[1]['type']);
        self::assertSame('', $frames[1]['content']);
        self::assertSame('completion', $frames[2]['type']);
        self::assertSame('cite me', $frames[2]['content']);
    }

    public function testItEndsOnlyOnce(): void
    {
        $this->sut->transform(new StreamStartEvent('s1', 'm'));

        self::assertNotSame([], $this->sut->end());
        self::assertSame([], $this->sut->end());
    }

    public function testItEmitsTheErrorFrame(): void
    {
        $frames = $this->sut->error(new \RuntimeException('boom'));

        self::assertSame('error', $frames[0]['type']);
        self::assertTrue($frames[0]['isDone']);
        self::assertSame('boom', $frames[0]['content']);
    }
}
