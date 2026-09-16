<?php
declare(strict_types=1);


namespace Tests\Unit\Services\Ai\LaravelAi\Drivers\OpenAiExtended;

use App\Services\Ai\LaravelAi\Drivers\OpenAiExtended\ExtendedOpenAiGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\ReasoningEnd;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\StreamStart;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Ai\LaravelAi\Drivers\OpenAiExtended\ExtendedOpenAiGatewayTest\ExtendedOpenAiGatewayTestFixtures\ExposedGateway;
use Tests\Unit\Services\Ai\LaravelAi\Drivers\OpenAiExtended\ExtendedOpenAiGatewayTest\ExtendedOpenAiGatewayTestFixtures\SseStreamFixture;

#[CoversClass(ExtendedOpenAiGateway::class)]
class ExtendedOpenAiGatewayTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeGateway(): ExposedGateway
    {
        return new ExposedGateway($this->createMock(Dispatcher::class));
    }

    /**
     * @return list<StreamEvent>
     */
    private function streamReasoningResponse(): array
    {
        $provider = $this->createMock(Provider::class);
        $provider->method('name')->willReturn('openai');

        $stream = SseStreamFixture::fromLines([
            'data: {"type":"response.created","response":{"id":"resp_1","model":"gpt-test"}}',
            '',
            'data: {"type":"response.reasoning_summary_text.delta","delta":"**Part One**"}',
            '',
            'data: {"type":"response.reasoning_summary_text.delta","delta":" first body"}',
            '',
            'data: {"type":"response.reasoning_summary_part.done"}',
            '',
            'data: {"type":"response.reasoning_summary_text.delta","delta":"**Part Two**"}',
            '',
            'data: {"type":"response.output_item.done","item":{"type":"reasoning","id":"rs_1","summary":[]}}',
            '',
            'data: {"type":"response.completed","response":{"id":"resp_1","output":[],"usage":[]}}',
            '',
            'data: [DONE]',
            '',
        ]);

        $events = [];
        foreach ($this->makeGateway()->exposeProcessTextStream('inv_1', $provider, 'gpt-test', $stream) as $event) {
            $events[] = $event;
        }

        return $events;
    }

    // =========================================================================
    // Reasoning summary part separation
    // =========================================================================

    public function testItSeparatesReasoningSummaryPartsWithABlankLine(): void
    {
        $events = $this->streamReasoningResponse();

        $deltas = array_values(array_filter(
            $events,
            static fn(StreamEvent $event) => $event instanceof ReasoningDelta
        ));

        static::assertSame(
            ['**Part One**', ' first body', "\n\n**Part Two**"],
            array_map(static fn(ReasoningDelta $delta) => $delta->delta, $deltas),
        );
    }

    public function testItKeepsOneLifecyclePerReasoningItem(): void
    {
        $events = $this->streamReasoningResponse();

        $ordered = array_map(static fn(StreamEvent $event) => $event::class, $events);

        static::assertSame(
            [
                StreamStart::class,
                ReasoningStart::class,
                ReasoningDelta::class,
                ReasoningDelta::class,
                ReasoningDelta::class,
                ReasoningEnd::class,
            ],
            $ordered,
        );

        $starts = array_values(array_filter($events, static fn(StreamEvent $event) => $event instanceof ReasoningStart));
        $ends   = array_values(array_filter($events, static fn(StreamEvent $event) => $event instanceof ReasoningEnd));

        static::assertCount(1, $starts);
        static::assertCount(1, $ends);

        /** @var ReasoningStart $start */
        $start = $starts[0];
        /** @var ReasoningEnd $end */
        $end = $ends[0];
        static::assertSame($start->reasoningId, $end->reasoningId);
    }
}
