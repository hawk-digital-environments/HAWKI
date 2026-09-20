<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Chat;

use App\Services\Ai\Chat\AiStreamNormalizer;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Stream\ContentBlockEndEvent;
use App\Services\Ai\Chat\Values\Stream\ContentBlockStartEvent;
use App\Services\Ai\Chat\Values\Stream\FinishEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiCitationEvent;
use App\Services\Ai\Chat\Values\Stream\ReasoningDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\StreamEndEvent;
use App\Services\Ai\Chat\Values\Stream\StreamStartEvent;
use App\Services\Ai\Chat\Values\Stream\TextDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallStartEvent;
use App\Services\Ai\Chat\Values\Stream\UsageEvent;
use App\Services\ExternalContent\CitationUrlCleaner;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall as VendorToolCall;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\ReasoningEnd;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;
use Laravel\Ai\Streaming\Events\ToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Clock\ClockInterface;
use Tests\TestCase;

#[CoversClass(AiStreamNormalizer::class)]
class AiStreamNormalizerTest extends TestCase
{
    private CitationUrlCleaner&MockObject $citationCleaner;
    private AiStreamNormalizer $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citationCleaner = $this->createMock(CitationUrlCleaner::class);
        $this->citationCleaner->method('clean')->willReturnCallback(static fn (\Laravel\Ai\Responses\Data\Citation $citation): \Laravel\Ai\Responses\Data\Citation => $citation);
        $this->citationCleaner->method('cleanMany')->willReturnCallback(static fn (array $citations): array => $citations);

        $clock = self::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $this->sut = new AiStreamNormalizer(
            citationCleaner: $this->citationCleaner,
            clock: $clock,
        );
    }

    public function testItConstructs(): void
    {
        self::assertInstanceOf(AiStreamNormalizer::class, $this->sut);
    }

    public function testItNormalizesTextBlockLifecycle(): void
    {
        $events = [...$this->sut->normalizeStream($this->vendorEvents([
            new StreamStart('s1', 'openai', 'gpt-4o', 1000),
            new TextStart('e1', 'm1', 1001),
            new TextDelta('e2', 'm1', 'Hel', 1002),
            new TextDelta('e3', 'm1', 'lo', 1003),
            new TextEnd('e4', 'm1', 1004),
        ]))];

        self::assertInstanceOf(StreamStartEvent::class, $events[0]);
        self::assertSame('s1', $events[0]->responseId);
        self::assertSame('gpt-4o', $events[0]->model);

        self::assertInstanceOf(ContentBlockStartEvent::class, $events[1]);
        self::assertSame(ContentBlockStartEvent::BLOCK_TYPE_TEXT, $events[1]->blockType);
        self::assertSame(0, $events[1]->blockIndex);

        self::assertInstanceOf(TextDeltaEvent::class, $events[2]);
        self::assertSame('Hel', $events[2]->text);
        self::assertSame(0, $events[2]->blockIndex);

        self::assertInstanceOf(TextDeltaEvent::class, $events[3]);
        self::assertSame('lo', $events[3]->text);

        self::assertInstanceOf(ContentBlockEndEvent::class, $events[4]);
        self::assertSame(0, $events[4]->blockIndex);
    }

    public function testItNormalizesReasoningBlocks(): void
    {
        $events = [...$this->sut->normalizeStream($this->vendorEvents([
            new ReasoningStart('e1', 'r1', 1000),
            new ReasoningDelta('e2', 'r1', 'thinking…', 1001),
            new ReasoningEnd('e3', 'r1', 1002),
        ]))];

        self::assertInstanceOf(ContentBlockStartEvent::class, $events[0]);
        self::assertSame(ContentBlockStartEvent::BLOCK_TYPE_THINKING, $events[0]->blockType);

        self::assertInstanceOf(ReasoningDeltaEvent::class, $events[1]);
        self::assertSame('thinking…', $events[1]->reasoning);

        self::assertInstanceOf(ContentBlockEndEvent::class, $events[2]);
    }

    public function testItNormalizesToolCallsAsStartAndDeltaPair(): void
    {
        $events = [...$this->sut->normalizeStream($this->vendorEvents([
            new ToolCall('e1', new VendorToolCall(id: 'call_1', name: 'web_search', arguments: ['query' => 'hi']), 1000),
        ]))];

        self::assertInstanceOf(ToolCallStartEvent::class, $events[0]);
        self::assertSame('call_1', $events[0]->toolCallId);
        self::assertSame('web_search', $events[0]->toolName);
        self::assertSame(0, $events[0]->toolCallIndex);

        self::assertInstanceOf(ToolCallDeltaEvent::class, $events[1]);
        self::assertSame('call_1', $events[1]->toolCallId);
        self::assertSame('{"query":"hi"}', $events[1]->argumentsDelta);
    }

    public function testItNormalizesCitationsThroughTheCleaner(): void
    {
        $this->citationCleaner->expects($this->once())->method('clean');

        $events = [...$this->sut->normalizeStream($this->vendorEvents([
            new Citation('e1', 'm1', new UrlCitation(url: 'https://example.com', title: 'Example'), 1000),
        ]))];

        self::assertInstanceOf(HawkiCitationEvent::class, $events[0]);
        self::assertSame('https://example.com', $events[0]->citation->url);
        self::assertSame('Example', $events[0]->citation->title);
    }

    public function testItNormalizesStreamEndIntoUsageFinishAndEnd(): void
    {
        $events = [...$this->sut->normalizeStream($this->vendorEvents([
            new StreamEnd('e1', 'stop', new Usage(promptTokens: 5, completionTokens: 7, reasoningTokens: 2), 1000),
        ]))];

        self::assertInstanceOf(UsageEvent::class, $events[0]);
        self::assertSame(5, $events[0]->usage->promptTokens);
        self::assertSame(7, $events[0]->usage->completionTokens);

        self::assertInstanceOf(FinishEvent::class, $events[1]);
        self::assertSame(FinishReasonType::STOP, $events[1]->finishReason->reason);

        self::assertInstanceOf(StreamEndEvent::class, $events[2]);
    }

    public function testItMapsToolCallFinishReasons(): void
    {
        $events = [...$this->sut->normalizeStream($this->vendorEvents([
            new StreamEnd('e1', 'tool_calls', new Usage(), 1000),
        ]))];

        self::assertInstanceOf(FinishEvent::class, $events[1]);
        self::assertSame(FinishReasonType::TOOL_CALLS, $events[1]->finishReason->reason);
    }

    public function testItThrowsOnVendorErrorEvents(): void
    {
        $this->expectException(\App\Services\Ai\Chat\Exceptions\ChatStreamFailedException::class);
        $this->expectExceptionMessage('upstream provider reported a stream error');

        [...$this->sut->normalizeStream($this->vendorEvents([
            new \Laravel\Ai\Streaming\Events\Error('e1', 'rate_limit', 'Too many requests', false, 1000),
        ]))];
    }

    public function testItNormalizesProviderToolEvents(): void
    {
        $events = [...$this->sut->normalizeStream($this->vendorEvents([
            new ProviderToolEvent('e1', 'ws_1', 'web_search_call', ['query' => 'hi'], 'completed', 1000),
        ]))];

        self::assertInstanceOf(\App\Services\Ai\Chat\Values\Stream\HawkiProviderToolEvent::class, $events[0]);
        self::assertSame('ws_1', $events[0]->itemId);
        self::assertSame('web_search_call', $events[0]->type);
        self::assertSame('completed', $events[0]->status);
    }

    public function testItNormalizesNonStreamingResponses(): void
    {
        $response = new AgentResponse(
            invocationId: 'inv_1',
            text: 'Hello',
            usage: new Usage(promptTokens: 3, completionTokens: 4),
            meta: new Meta(provider: 'openai', model: 'gpt-4o'),
        );
        $response->toolCalls = collect([
            new VendorToolCall(id: 'call_1', name: 'web_search', arguments: ['q' => 1]),
        ]);

        $result = $this->sut->normalizeResponse($response);

        self::assertSame('inv_1', $result->id);
        self::assertSame('gpt-4o', $result->model);
        self::assertSame(FinishReasonType::STOP, $result->finishReason->reason);
        self::assertSame(3, $result->usage?->promptTokens);

        $toolCalls = $result->message->toolCalls();
        self::assertCount(1, $toolCalls);
        self::assertSame('web_search', $toolCalls[0]->toolName);
        self::assertSame('Hello', $result->message->text());
    }

    public function testItSurfacesToolCallReasoningStateAsProviderMetadata(): void
    {
        $response = new AgentResponse(
            invocationId: 'inv_2',
            text: '',
            usage: new Usage(),
            meta: new Meta(provider: 'openai', model: 'o4-mini'),
        );
        $response->toolCalls = collect([
            new VendorToolCall(
                id: 'fc_1',
                name: 'read_file',
                arguments: [],
                resultId: 'call_1',
                reasoningId: 'rs_1',
                reasoningSummary: [['type' => 'summary_text', 'text' => 'pondering']],
                reasoningEncryptedContent: 'enc-1',
            ),
        ]);

        $result = $this->sut->normalizeResponse($response);

        $metadata = $result->message->toolCalls()[0]->providerMetadata;
        self::assertSame('rs_1', $metadata['reasoning_id'] ?? null);
        self::assertSame('pondering', $metadata['reasoning_summary'] ?? null);
        self::assertSame('enc-1', $metadata['reasoning_encrypted_content'] ?? null);
    }

    /**
     * @param array<int, object> $events
     *
     * @return \Generator<int, object>
     */
    private function vendorEvents(array $events): \Generator
    {
        yield from $events;
    }
}
