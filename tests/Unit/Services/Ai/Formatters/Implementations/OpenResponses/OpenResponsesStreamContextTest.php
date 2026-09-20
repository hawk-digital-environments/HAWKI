<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Implementations\OpenResponses;

use App\Services\Ai\Chat\Values\FinishReason;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Stream\ContentBlockEndEvent;
use App\Services\Ai\Chat\Values\Stream\ContentBlockStartEvent;
use App\Services\Ai\Chat\Values\Stream\FinishEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiCitationEvent;
use App\Services\Ai\Chat\Values\Stream\StreamEndEvent;
use App\Services\Ai\Chat\Values\Stream\StreamStartEvent;
use App\Services\Ai\Chat\Values\Stream\TextDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallStartEvent;
use App\Services\Ai\Chat\Values\Stream\UsageEvent;
use App\Services\Ai\Chat\Values\UsageInfo;
use App\Services\Ai\Formatters\Implementations\OpenResponses\OpenResponsesStreamContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenResponsesStreamContext::class)]
class OpenResponsesStreamContextTest extends TestCase
{
    private OpenResponsesStreamContext $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new OpenResponsesStreamContext(responseId: 'resp_test', createdAt: 1700000000);
    }

    public function testItConstructs(): void
    {
        self::assertInstanceOf(OpenResponsesStreamContext::class, $this->sut);
    }

    public function testItEmitsCreatedAndInProgressOnStreamStart(): void
    {
        $frames = $this->sut->transform(new StreamStartEvent(responseId: 's1', model: 'gpt-4o', created: 1700000000));

        self::assertCount(2, $frames);
        self::assertSame('response.created', $frames[0]['event']);
        self::assertSame('response.in_progress', $frames[1]['event']);

        self::assertSame('resp_s1', $frames[0]['data']['response']['id']);
        self::assertSame('response', $frames[0]['data']['response']['object']);
        self::assertSame('gpt-4o', $frames[0]['data']['response']['model']);
        self::assertSame(0, $frames[0]['data']['sequence_number']);
        self::assertSame(1, $frames[1]['data']['sequence_number']);
    }

    public function testItBuildsTheMessageItemLifecycle(): void
    {
        $frames = [
            ...$this->sut->transform(new ContentBlockStartEvent(0, ContentBlockStartEvent::BLOCK_TYPE_TEXT)),
            ...$this->sut->transform(new TextDeltaEvent('Hel', 0)),
            ...$this->sut->transform(new TextDeltaEvent('lo', 0)),
            ...$this->sut->transform(new ContentBlockEndEvent(0)),
        ];

        $events = array_map(static fn (array $frame): string => $frame['event'], $frames);

        self::assertSame([
            'response.output_item.added',
            'response.content_part.added',
            'response.output_text.delta',
            'response.output_text.delta',
            'response.output_text.done',
            'response.content_part.done',
            'response.output_item.done',
        ], $events);

        $deltas = array_values(array_filter($frames, static fn (array $frame): bool => 'response.output_text.delta' === $frame['event']));
        self::assertSame('Hel', $deltas[0]['data']['delta']);
        self::assertSame(0, $deltas[0]['data']['output_index']);
        self::assertStringStartsWith('msg_', $deltas[0]['data']['item_id']);

        $itemDone = $frames[6];
        self::assertSame('completed', $itemDone['data']['item']['status']);
        self::assertSame('Hello', $itemDone['data']['item']['content'][0]['text']);
    }

    public function testItBuildsTheReasoningItemLifecycle(): void
    {
        $frames = [
            ...$this->sut->transform(new ContentBlockStartEvent(0, ContentBlockStartEvent::BLOCK_TYPE_THINKING)),
            ...$this->sut->transform(new \App\Services\Ai\Chat\Values\Stream\ReasoningDeltaEvent('hm', blockIndex: 0)),
            ...$this->sut->transform(new ContentBlockEndEvent(0)),
        ];

        $events = array_map(static fn (array $frame): string => $frame['event'], $frames);

        self::assertSame([
            'response.output_item.added',
            'response.reasoning_summary_part.added',
            'response.reasoning_summary_text.delta',
            'response.reasoning_summary_text.done',
            'response.reasoning_summary_part.done',
            'response.output_item.done',
        ], $events);

        $itemDone = $frames[5];
        self::assertSame('reasoning', $itemDone['data']['item']['type']);
        self::assertSame('hm', $itemDone['data']['item']['summary'][0]['text']);
    }

    public function testItBuildsTheFunctionCallItemLifecycle(): void
    {
        $frames = [
            ...$this->sut->transform(new ToolCallStartEvent('call_1', 'web_search', toolCallIndex: 0, blockIndex: 1)),
            ...$this->sut->transform(new ToolCallDeltaEvent('call_1', '{"q":1}', toolCallIndex: 0, blockIndex: 1)),
        ];

        $events = array_map(static fn (array $frame): string => $frame['event'], $frames);

        self::assertSame([
            'response.output_item.added',
            'response.function_call_arguments.delta',
        ], $events);

        self::assertSame('function_call', $frames[0]['data']['item']['type']);
        self::assertSame('call_1', $frames[0]['data']['item']['call_id']);
        self::assertSame('web_search', $frames[0]['data']['item']['name']);
    }

    public function testItEmitsCitationsAsNativeAnnotationEvents(): void
    {
        $this->sut->transform(new StreamStartEvent('s1', 'gpt-4o'));
        $this->sut->transform(new TextDeltaEvent('Some claim'));

        $frames = $this->sut->transform(new HawkiCitationEvent(new \App\Services\Ai\Chat\Values\CitationData(url: 'https://example.com', title: 'Example', startIndex: 0, endIndex: 4)));

        self::assertCount(1, $frames);
        self::assertSame('response.output_text.annotation.added', $frames[0]['event']);
        self::assertSame('response.output_text.annotation.added', $frames[0]['data']['type']);
        self::assertSame('url_citation', $frames[0]['data']['annotation']['type']);
        self::assertSame('https://example.com', $frames[0]['data']['annotation']['url']);
        self::assertSame(0, $frames[0]['data']['annotation_index']);

        // The annotation rides the item lifecycle into the final resource.
        $this->sut->transform(new StreamEndEvent());
        $completed = $this->sut->end();
        $resource = null;

        foreach ($completed as $frame) {
            if ('response.completed' === $frame['event']) {
                $resource = $frame['data']['response'];
            }
        }

        self::assertSame(
            'https://example.com',
            $resource['output'][0]['content'][0]['annotations'][0]['url'],
        );
    }

    public function testItDropsCitationsWithoutAnyMessageItem(): void
    {
        $this->sut->transform(new StreamStartEvent('s1', 'gpt-4o'));

        $frames = $this->sut->transform(new HawkiCitationEvent(new \App\Services\Ai\Chat\Values\CitationData(url: 'https://example.com')));

        self::assertSame([], $frames);
    }

    public function testItMergesUsageAndFinishIntoSingleTerminalEvent(): void
    {
        $frames = [
            ...$this->sut->transform(new StreamStartEvent('s1', 'gpt-4o')),
            ...$this->sut->transform(new UsageEvent(new UsageInfo(promptTokens: 2, completionTokens: 3, totalTokens: 5))),
            ...$this->sut->transform(new FinishEvent(FinishReason::stop())),
            ...$this->sut->transform(new StreamEndEvent()),
        ];

        $completed = array_values(array_filter($frames, static fn (array $frame): bool => 'response.completed' === $frame['event']));
        self::assertCount(1, $completed);

        $usage = $completed[0]['data']['response']['usage'];
        self::assertSame(2, $usage['input_tokens']);
        self::assertSame(3, $usage['output_tokens']);
        self::assertSame(5, $usage['total_tokens']);
    }

    public function testItEmitsIncompleteOnLengthFinish(): void
    {
        $frames = [
            ...$this->sut->transform(new StreamStartEvent('s1', 'gpt-4o')),
            ...$this->sut->transform(new FinishEvent(new FinishReason(FinishReasonType::LENGTH))),
            ...$this->sut->transform(new StreamEndEvent()),
        ];

        $incomplete = array_values(array_filter($frames, static fn (array $frame): bool => 'response.incomplete' === $frame['event']));
        self::assertCount(1, $incomplete);
        self::assertSame('max_output_tokens', $incomplete[0]['data']['response']['incomplete_details']['reason']);
    }

    public function testItEmitsErrorAndFailedEvents(): void
    {
        $frames = [
            ...$this->sut->transform(new StreamStartEvent('s1', 'gpt-4o')),
            ...$this->sut->error(new \RuntimeException('boom')),
        ];

        self::assertSame('error', $frames[2]['event']);
        self::assertSame('boom', $frames[2]['data']['error']['message']);

        self::assertSame('response.failed', $frames[3]['event']);
        self::assertSame('failed', $frames[3]['data']['response']['status']);
        self::assertSame('boom', $frames[3]['data']['response']['error']['message']);
    }

    public function testItEmitsLifecycleEventsWhenErrorOccursBeforeStreamStart(): void
    {
        $frames = $this->sut->error(new \RuntimeException('boom'));

        self::assertSame('response.created', $frames[0]['event']);
        self::assertSame('error', $frames[2]['event']);
        self::assertSame('response.failed', $frames[3]['event']);
    }

    public function testItKeepsSequenceNumbersMonotonic(): void
    {
        $frames = [
            ...$this->sut->transform(new StreamStartEvent('s1', 'gpt-4o')),
            ...$this->sut->transform(new ContentBlockStartEvent(0, ContentBlockStartEvent::BLOCK_TYPE_TEXT)),
            ...$this->sut->transform(new TextDeltaEvent('x', 0)),
            ...$this->sut->transform(new ContentBlockEndEvent(0)),
            ...$this->sut->transform(new FinishEvent(FinishReason::stop())),
            ...$this->sut->transform(new StreamEndEvent()),
        ];

        $previous = -1;

        foreach ($frames as $frame) {
            self::assertGreaterThan($previous, $frame['data']['sequence_number']);
            $previous = $frame['data']['sequence_number'];
        }
    }
}
