<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Implementations\OpenAiChatCompletions;

use App\Services\Ai\Chat\Values\CitationData;
use App\Services\Ai\Chat\Values\FinishReason;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Stream\FinishEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiCitationEvent;
use App\Services\Ai\Chat\Values\Stream\ReasoningDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\StreamEndEvent;
use App\Services\Ai\Chat\Values\Stream\StreamStartEvent;
use App\Services\Ai\Chat\Values\Stream\TextDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallStartEvent;
use App\Services\Ai\Chat\Values\Stream\UsageEvent;
use App\Services\Ai\Chat\Values\UsageInfo;
use App\Services\Ai\Formatters\Implementations\OpenAiChatCompletions\OpenAiChatCompletionsStreamContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenAiChatCompletionsStreamContext::class)]
class OpenAiChatCompletionsStreamContextTest extends TestCase
{
    public function testItDefersTheRoleChunkUntilTheFirstDelta(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();

        self::assertSame([], $context->transform(new StreamStartEvent('s1', 'gpt-4o', 1000)));

        $chunks = $context->transform(new TextDeltaEvent('Hel', 0));

        self::assertSame(['role' => 'assistant', 'content' => 'Hel'], $chunks[0]['choices'][0]['delta']);
        self::assertSame('chatcmpl-s1', $chunks[0]['id']);
        self::assertSame('chat.completion.chunk', $chunks[0]['object']);
        self::assertSame('gpt-4o', $chunks[0]['model']);
        self::assertSame(1000, $chunks[0]['created']);

        $second = $context->transform(new TextDeltaEvent('lo', 0));
        self::assertSame(['content' => 'lo'], $second[0]['choices'][0]['delta']);
    }

    public function testItFlushesFinishAndUsageOnEnd(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();
        $context->transform(new StreamStartEvent('s1', 'm'));
        $context->transform(new TextDeltaEvent('Hi', 0));
        $context->transform(new UsageEvent(new UsageInfo(promptTokens: 5, completionTokens: 7, totalTokens: 12)));
        $context->transform(new FinishEvent(FinishReason::stop()));
        $context->transform(new StreamEndEvent());

        $chunks = $context->end();

        self::assertCount(2, $chunks);
        self::assertSame([], $chunks[0]['choices'][0]['delta']);
        self::assertSame('stop', $chunks[0]['choices'][0]['finish_reason']);
        self::assertSame([], $chunks[1]['choices']);
        self::assertSame(['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12], $chunks[1]['usage']);
        self::assertArrayNotHasKey('usage', $chunks[0]);
    }

    public function testItDefaultsToStopWhenNoFinishEventArrives(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();

        $chunks = $context->end();

        self::assertSame('stop', $chunks[0]['choices'][0]['finish_reason']);
    }

    public function testItEndsOnlyOnce(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();
        $context->transform(new FinishEvent(FinishReason::stop()));

        self::assertNotSame([], $context->end());
        self::assertSame([], $context->end());
    }

    public function testItOmitsTheUsageChunkWhenUsageIsExcluded(): void
    {
        $context = new OpenAiChatCompletionsStreamContext(includeUsage: false);
        $context->transform(new UsageEvent(new UsageInfo(promptTokens: 5, completionTokens: 7, totalTokens: 12)));

        $chunks = $context->end();

        self::assertCount(1, $chunks);
    }

    public function testItOmitsTheUsageChunkWhenNoUsageArrived(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();

        self::assertCount(1, $context->end());
    }

    public function testItAddressesToolCallDeltasByIndex(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();
        $context->transform(new StreamStartEvent('s1', 'm'));

        $start = $context->transform(new ToolCallStartEvent('call_1', 'read_file', toolCallIndex: 0))[0];
        self::assertSame([
            'role' => 'assistant',
            'tool_calls' => [[
                'index' => 0,
                'id' => 'call_1',
                'type' => 'function',
                'function' => ['name' => 'read_file', 'arguments' => ''],
            ]],
        ], $start['choices'][0]['delta']);

        $delta = $context->transform(new ToolCallDeltaEvent('call_1', '{"a":', toolCallIndex: 0))[0];
        self::assertSame([
            'tool_calls' => [[
                'index' => 0,
                'function' => ['arguments' => '{"a":'],
            ]],
        ], $delta['choices'][0]['delta']);

        $second = $context->transform(new ToolCallStartEvent('call_2', 'write_file', toolCallIndex: 1))[0];
        self::assertSame(1, $second['choices'][0]['delta']['tool_calls'][0]['index']);
        self::assertSame('call_2', $second['choices'][0]['delta']['tool_calls'][0]['id']);
    }

    public function testItRecoversAnOrphanToolCallDeltaByAssigningAnIndex(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();

        $delta = $context->transform(new ToolCallDeltaEvent('call_x', '{"b":1}', toolCallIndex: null))[0];

        self::assertSame(0, $delta['choices'][0]['delta']['tool_calls'][0]['index']);
        self::assertSame('{"b":1}', $delta['choices'][0]['delta']['tool_calls'][0]['function']['arguments']);
    }

    public function testItStreamsReasoningDeltasAsReasoningContent(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();

        $chunk = $context->transform(new ReasoningDeltaEvent('pondering'))[0];

        self::assertSame(['role' => 'assistant', 'reasoning_content' => 'pondering'], $chunk['choices'][0]['delta']);
    }

    public function testItEmitsCitationsAsCustomFramesAndSuppressesThemWhenDisabled(): void
    {
        $citation = new HawkiCitationEvent(new CitationData(url: 'https://x', title: 'X'));

        $enabled = new OpenAiChatCompletionsStreamContext(emitCustomEvents: true);
        self::assertSame('hawki:citation', $enabled->transform($citation)[0]['type']);

        $disabled = new OpenAiChatCompletionsStreamContext(emitCustomEvents: false);
        self::assertSame([], $disabled->transform($citation));
    }

    public function testItIgnoresContentBlockBoundaries(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();

        self::assertSame([], $context->transform(new \App\Services\Ai\Chat\Values\Stream\ContentBlockStartEvent(0, 'text')));
        self::assertSame([], $context->transform(new \App\Services\Ai\Chat\Values\Stream\ContentBlockEndEvent(0)));
    }

    public function testItMapsFinishReasons(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();
        $context->transform(new FinishEvent(new FinishReason(FinishReasonType::TOOL_CALLS)));

        self::assertSame('tool_calls', $context->end()[0]['choices'][0]['finish_reason']);
    }

    public function testItEmitsAnErrorFrame(): void
    {
        $context = new OpenAiChatCompletionsStreamContext();

        $frames = $context->error(new \RuntimeException('boom'));

        self::assertSame('server_error', $frames[0]['error']['type']);
        self::assertSame('boom', $frames[0]['error']['message']);
    }
}
