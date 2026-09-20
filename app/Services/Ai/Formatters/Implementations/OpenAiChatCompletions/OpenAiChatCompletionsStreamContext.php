<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Implementations\OpenAiChatCompletions;

use App\Services\Ai\Chat\Values\CitationData;
use App\Services\Ai\Chat\Values\FinishReason;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Stream\AiStreamEvent;
use App\Services\Ai\Chat\Values\Stream\ContentBlockEndEvent;
use App\Services\Ai\Chat\Values\Stream\ContentBlockStartEvent;
use App\Services\Ai\Chat\Values\Stream\FinishEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiCitationEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiProviderToolEvent;
use App\Services\Ai\Chat\Values\Stream\ReasoningDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\RefusalDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\StreamEndEvent;
use App\Services\Ai\Chat\Values\Stream\StreamStartEvent;
use App\Services\Ai\Chat\Values\Stream\TextDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallStartEvent;
use App\Services\Ai\Chat\Values\Stream\UsageEvent;
use App\Services\Ai\Chat\Values\UsageInfo;
use Illuminate\Support\Str;

/**
 * Stateful translator from IR stream events to OpenAI Chat Completions chunks.
 *
 * Owns the per-stream bookkeeping this wire format demands: bare `data:` frames with
 * no `event:` line, the deferred role chunk (merged into the first delta-bearing
 * event), tool-call deltas addressed by a sequential chunk `index` (id present only on
 * the first fragment of each call), and the deferred finish/usage flush at stream end.
 *
 * One IR event maps to 0..n chunks. Modeled on LLM-Rosetta's StreamContext and the
 * Open Responses context ({@see \App\Services\Ai\Formatters\Implementations\OpenResponses\OpenResponsesStreamContext}).
 */
class OpenAiChatCompletionsStreamContext
{
    private string $chunkId;
    private string $model = '';
    private int $createdAt;
    private bool $roleEmitted = false;

    /**
     * toolCallId => chunk index.
     *
     * @var array<string, int>
     */
    private array $toolCallIndexes = [];

    /**
     * @var array<int, string>
     */
    private array $indexToCallId = [];
    private ?FinishReason $pendingFinish = null;
    private ?UsageInfo $pendingUsage = null;
    private bool $ended = false;

    public function __construct(
        private readonly bool $includeUsage = true,
        private readonly bool $emitCustomEvents = true,
    ) {
        $this->createdAt = time();
        $this->chunkId = 'chatcmpl-' . Str::uuid()->toString();
    }

    /**
     * Translates one IR stream event into 0..n wire-format chunks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function transform(AiStreamEvent $event): array
    {
        return match (true) {
            $event instanceof StreamStartEvent => $this->handleStreamStart($event),
            $event instanceof ContentBlockStartEvent, $event instanceof ContentBlockEndEvent => [],
            $event instanceof TextDeltaEvent => $this->handleTextDelta($event),
            $event instanceof ReasoningDeltaEvent => $this->handleReasoningDelta($event),
            $event instanceof RefusalDeltaEvent => $this->handleRefusalDelta($event),
            $event instanceof ToolCallStartEvent => $this->handleToolCallStart($event),
            $event instanceof ToolCallDeltaEvent => $this->handleToolCallDelta($event),
            $event instanceof HawkiCitationEvent => $this->handleCitation($event->citation),
            $event instanceof HawkiProviderToolEvent => $this->handleProviderToolEvent($event),
            $event instanceof UsageEvent => $this->bufferUsage($event),
            $event instanceof FinishEvent => $this->bufferFinish($event),
            $event instanceof StreamEndEvent => [],
            default => [],
        };
    }

    /**
     * Flushes the terminal finish chunk (and, when usage is included, the trailing
     * usage-only chunk). The formatter writes `data: [DONE]` afterwards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function end(): array
    {
        if ($this->ended) {
            return [];
        }

        $this->ended = true;

        $chunks = [$this->chunk(
            delta: [],
            extra: ['finish_reason' => OpenAiChatCompletionsFormatter::finishReason($this->pendingFinish->reason ?? FinishReasonType::STOP)],
        )];

        if ($this->includeUsage && null !== $this->pendingUsage) {
            $chunks[] = $this->usageChunk();
        }

        return $chunks;
    }

    /**
     * Emits the error chunk that mid-stream failures degrade to.
     *
     * @return array<int, array<string, mixed>>
     */
    public function error(\Throwable $exception): array
    {
        $message = '' !== $exception->getMessage() ? $exception->getMessage() : 'The upstream provider request failed.';

        $this->ended = true;

        return [[
            'error' => [
                'message' => $message,
                'type' => 'server_error',
                'param' => null,
                'code' => null,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleStreamStart(StreamStartEvent $event): array
    {
        $this->model = $event->model;
        $this->createdAt = $event->created ?? $this->createdAt;
        $this->chunkId = OpenAiChatCompletionsFormatter::ensureChunkIdPrefix($event->responseId);

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleTextDelta(TextDeltaEvent $event): array
    {
        return [$this->deltaChunk(['content' => $event->text])];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleReasoningDelta(ReasoningDeltaEvent $event): array
    {
        return [$this->deltaChunk(['reasoning_content' => $event->reasoning])];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleRefusalDelta(RefusalDeltaEvent $event): array
    {
        return [$this->deltaChunk(['refusal' => $event->refusal])];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleToolCallStart(ToolCallStartEvent $event): array
    {
        $index = $this->toolCallIndexFor($event->toolCallId, $event->toolCallIndex);

        $chunk = $this->deltaChunk([
            'tool_calls' => [[
                'index' => $index,
                'id' => $event->toolCallId,
                'type' => 'function',
                'function' => [
                    'name' => $event->toolName,
                    'arguments' => '',
                ],
            ]],
        ]);

        return [$chunk];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleToolCallDelta(ToolCallDeltaEvent $event): array
    {
        $index = $this->toolCallIndexFor($event->toolCallId, $event->toolCallIndex);

        $chunk = $this->deltaChunk([
            'tool_calls' => [[
                'index' => $index,
                'function' => [
                    'arguments' => $event->argumentsDelta,
                ],
            ]],
        ]);

        return [$chunk];
    }

    /**
     * Custom non-standard frame (the format has no native citation slot); suppressed
     * when custom events are disabled globally.
     *
     * @return array<int, array<string, mixed>>
     */
    private function handleCitation(CitationData $citation): array
    {
        if (!$this->emitCustomEvents) {
            return [];
        }

        return [[
            'type' => 'hawki:citation',
            'citation' => [
                'url' => $citation->url,
                'title' => $citation->title,
                'start_index' => $citation->startIndex,
                'end_index' => $citation->endIndex,
            ],
        ]];
    }

    /**
     * Custom non-standard frame; suppressed when custom events are disabled globally.
     *
     * @return array<int, array<string, mixed>>
     */
    private function handleProviderToolEvent(HawkiProviderToolEvent $event): array
    {
        if (!$this->emitCustomEvents) {
            return [];
        }

        return [[
            'type' => 'hawki:provider_tool_event',
            'item_id' => $event->itemId,
            'event_type' => $event->type,
            'data' => $event->data,
            'status' => $event->status,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bufferUsage(UsageEvent $event): array
    {
        $this->pendingUsage = $event->usage;

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bufferFinish(FinishEvent $event): array
    {
        $this->pendingFinish = $event->finishReason;

        return [];
    }

    /**
     * Resolves the wire chunk index for a tool call id, assigning the next sequential
     * index when the call has not been seen yet (start-event recovery for orphan
     * deltas, mirroring Rosetta's `_resolve_tool_call_delta`).
     */
    private function toolCallIndexFor(string $toolCallId, ?int $toolCallIndex): int
    {
        if (isset($this->toolCallIndexes[$toolCallId])) {
            return $this->toolCallIndexes[$toolCallId];
        }

        $index = $toolCallIndex ?? \count($this->toolCallIndexes);

        // Slot already taken by another call id (driver reused an index): append.
        while (isset($this->indexToCallId[$index]) && $this->indexToCallId[$index] !== $toolCallId) {
            ++$index;
        }

        $this->toolCallIndexes[$toolCallId] = $index;
        $this->indexToCallId[$index] = $toolCallId;

        return $index;
    }

    /**
     * @param array<string, mixed> $delta
     */
    private function deltaChunk(array $delta): array
    {
        return $this->chunk(delta: $this->withRole($delta));
    }

    /**
     * Merges the deferred role fragment into the first delta-bearing chunk.
     *
     * @param array<string, mixed> $delta
     *
     * @return array<string, mixed>
     */
    private function withRole(array $delta): array
    {
        if ($this->roleEmitted) {
            return $delta;
        }

        $this->roleEmitted = true;

        return ['role' => 'assistant', ...$delta];
    }

    /**
     * @param array<string, mixed> $delta
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function chunk(array $delta, array $extra = []): array
    {
        return [
            'id' => $this->chunkId,
            'object' => 'chat.completion.chunk',
            'created' => $this->createdAt,
            'model' => $this->model,
            'system_fingerprint' => null,
            'choices' => [
                [
                    'index' => 0,
                    'delta' => $delta,
                    'finish_reason' => null,
                    ...$extra,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usageChunk(): array
    {
        $usage = $this->pendingUsage;

        return [
            'id' => $this->chunkId,
            'object' => 'chat.completion.chunk',
            'created' => $this->createdAt,
            'model' => $this->model,
            'system_fingerprint' => null,
            'choices' => [],
            'usage' => [
                'prompt_tokens' => $usage->promptTokens,
                'completion_tokens' => $usage->completionTokens,
                'total_tokens' => $usage->totalTokens,
            ],
        ];
    }
}
