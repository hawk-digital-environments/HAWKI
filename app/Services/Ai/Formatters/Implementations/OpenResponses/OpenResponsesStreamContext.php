<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Implementations\OpenResponses;

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
 * Stateful translator from IR stream events to Open Responses SSE event frames.
 *
 * Owns the per-stream bookkeeping the wire format demands: a monotonic
 * {@see sequence_number}, the output-item and content-part lifecycles
 * (`response.output_item.added` → deltas → `response.output_item.done`), tool-call
 * argument accumulation, and the deferred usage / finish reason that are merged into the
 * single terminal lifecycle event (never duplicated).
 *
 * One IR event maps to 0..n frames. Modeled on LLM-Rosetta's StreamContext.
 */
class OpenResponsesStreamContext
{
    private int $sequenceNumber = 0;
    private string $responseId;
    private string $model = '';
    private int $createdAt;
    private bool $started = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $outputItems = [];

    /**
     * @var array<int, array{kind: string, itemId: string, outputIndex: int, text: string, annotations: list<array<string, mixed>>, callId: string, name: string, arguments: string}>
     */
    private array $openItems = [];
    private ?UsageInfo $pendingUsage = null;
    private ?FinishReason $pendingFinish = null;

    public function __construct(
        ?string $responseId = null,
        ?int $createdAt = null,
        private readonly bool $emitCustomEvents = true,
    ) {
        $this->responseId = $responseId ?? 'resp_' . Str::uuid()->toString();
        $this->createdAt = $createdAt ?? time();
    }

    /**
     * Translates one IR stream event into 0..n wire-format frames.
     *
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    public function transform(AiStreamEvent $event): array
    {
        return match (true) {
            $event instanceof StreamStartEvent => $this->handleStreamStart($event),
            $event instanceof ContentBlockStartEvent => $this->handleContentBlockStart($event),
            $event instanceof TextDeltaEvent => $this->handleTextDelta($event),
            $event instanceof ReasoningDeltaEvent => $this->handleReasoningDelta($event),
            $event instanceof RefusalDeltaEvent => [],
            $event instanceof ContentBlockEndEvent => $this->handleContentBlockEnd($event),
            $event instanceof ToolCallStartEvent => $this->handleToolCallStart($event),
            $event instanceof ToolCallDeltaEvent => $this->handleToolCallDelta($event),
            $event instanceof HawkiCitationEvent => $this->handleCitation($event->citation),
            $event instanceof HawkiProviderToolEvent => $this->handleProviderToolEvent($event),
            $event instanceof UsageEvent => $this->bufferUsage($event),
            $event instanceof FinishEvent => $this->bufferFinish($event),
            $event instanceof StreamEndEvent => $this->end(),
            default => [],
        };
    }

    /**
     * Emits the terminal lifecycle event, closing any still-open output items.
     *
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    public function end(): array
    {
        $frames = $this->closeOpenItems();

        $finish = $this->pendingFinish ?? new FinishReason(\App\Services\Ai\Chat\Values\FinishReasonType::STOP);
        $status = 'completed';
        $incompleteDetails = null;

        if (FinishReasonType::LENGTH === $finish->reason) {
            $status = 'incomplete';
            $incompleteDetails = ['reason' => 'max_output_tokens'];
        } elseif (FinishReasonType::CONTENT_FILTER === $finish->reason) {
            $status = 'incomplete';
            $incompleteDetails = ['reason' => 'content_filter'];
        }

        $event = 'incomplete' === $status ? 'response.incomplete' : 'response.completed';

        $frames[] = $this->frame($event, [
            'response' => OpenResponsesResource::resource(
                id: $this->responseId,
                model: $this->model,
                createdAt: $this->createdAt,
                output: $this->outputItems,
                status: $status,
                incompleteDetails: $incompleteDetails,
                usage: $this->pendingUsage,
            ),
        ]);

        return $frames;
    }

    /**
     * Emits the error event plus the required follow-up `response.failed` event.
     *
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    public function error(\Throwable $exception): array
    {
        $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'The upstream provider request failed.';

        $frames = [];

        if (!$this->started) {
            $frames = [...$frames, ...$this->handleStreamStart(new StreamStartEvent(
                responseId: $this->responseId,
                model: $this->model,
            ))];
        }

        $frames[] = $this->frame('error', [
            'error' => [
                'type' => 'server_error',
                'code' => null,
                'message' => $message,
                'param' => null,
            ],
        ]);

        $frames[] = $this->frame('response.failed', [
            'response' => OpenResponsesResource::resource(
                id: $this->responseId,
                model: $this->model,
                createdAt: $this->createdAt,
                output: $this->outputItems,
                status: 'failed',
                error: ['code' => null, 'message' => $message],
                usage: $this->pendingUsage,
            ),
        ]);

        return $frames;
    }

    public function responseId(): string
    {
        return $this->responseId;
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleStreamStart(StreamStartEvent $event): array
    {
        $this->started = true;
        $this->model = $event->model;
        $this->createdAt = $event->created ?? $this->createdAt;
        $this->responseId = self::ensureResponseIdPrefix($event->responseId);

        $response = OpenResponsesResource::resource(
            id: $this->responseId,
            model: $this->model,
            createdAt: $this->createdAt,
            output: [],
        );

        return [
            $this->frame('response.created', ['response' => $response]),
            $this->frame('response.in_progress', ['response' => $response]),
        ];
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleContentBlockStart(ContentBlockStartEvent $event): array
    {
        return match ($event->blockType) {
            ContentBlockStartEvent::BLOCK_TYPE_TEXT => $this->openMessageItem($event->blockIndex),
            ContentBlockStartEvent::BLOCK_TYPE_THINKING => $this->openReasoningItem($event->blockIndex),
            default => [],
        };
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleTextDelta(TextDeltaEvent $event): array
    {
        $index = $event->blockIndex ?? 0;

        $frames = isset($this->openItems[$index])
            ? []
            : $this->openMessageItem($index);

        $item = $this->openItems[$index];
        $item['text'] .= $event->text;
        $this->openItems[$index] = $item;

        $frames[] = $this->frame('response.output_text.delta', [
            'item_id' => $item['itemId'],
            'output_index' => $item['outputIndex'],
            'content_index' => 0,
            'delta' => $event->text,
        ]);

        return $frames;
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleReasoningDelta(ReasoningDeltaEvent $event): array
    {
        $index = $event->blockIndex ?? 0;

        $frames = isset($this->openItems[$index])
            ? []
            : $this->openReasoningItem($index);

        $item = $this->openItems[$index];
        $item['text'] .= $event->reasoning;
        $this->openItems[$index] = $item;

        $frames[] = $this->frame('response.reasoning_summary_text.delta', [
            'item_id' => $item['itemId'],
            'output_index' => $item['outputIndex'],
            'summary_index' => 0,
            'delta' => $event->reasoning,
        ]);

        return $frames;
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleContentBlockEnd(ContentBlockEndEvent $event): array
    {
        $item = $this->openItems[$event->blockIndex] ?? null;

        return null !== $item ? $this->closeItem($event->blockIndex) : [];
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleToolCallStart(ToolCallStartEvent $event): array
    {
        $index = $event->blockIndex ?? (1000 + ($event->toolCallIndex ?? 0));

        return isset($this->openItems[$index])
            ? []
            : $this->openFunctionCallItem($index, $event->toolCallId, $event->toolName);
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleToolCallDelta(ToolCallDeltaEvent $event): array
    {
        $index = $event->blockIndex ?? (1000 + ($event->toolCallIndex ?? 0));

        if (!isset($this->openItems[$index])) {
            $openFrames = $this->openFunctionCallItem($index, $event->toolCallId, 'unknown');
        } else {
            $openFrames = [];
        }

        $item = $this->openItems[$index];
        $item['arguments'] .= $event->argumentsDelta;
        $this->openItems[$index] = $item;

        return [...$openFrames, $this->frame('response.function_call_arguments.delta', [
            'item_id' => $item['itemId'],
            'output_index' => $item['outputIndex'],
            'delta' => $event->argumentsDelta,
        ])];
    }

    /**
     * Emits the spec-native annotation event (`response.output_text.annotation.added`)
     * for a citation, attaching the url_citation to the current message item so the
     * part/item lifecycle frames and the final resource carry populated annotations.
     *
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleCitation(CitationData $citation): array
    {
        $annotation = [
            'type' => 'url_citation',
            'url' => $citation->url,
            'title' => $citation->title,
            'start_index' => $citation->startIndex,
            'end_index' => $citation->endIndex,
        ];

        $index = $this->openMessageIndex();

        if (null === $index) {
            // No open message item: attach to the last settled one so the completed
            // resource still carries the annotation (event references that item).
            $settledKey = null;

            foreach ($this->outputItems as $key => $item) {
                if ('message' === ($item['type'] ?? '')) {
                    $settledKey = $key;
                }
            }

            if (null === $settledKey) {
                return [];
            }

            $annotationIndex = $this->appendSettledAnnotation($settledKey, $annotation);

            return [$this->frame('response.output_text.annotation.added', [
                'item_id' => $this->outputItems[$settledKey]['id'],
                'output_index' => $settledKey,
                'content_index' => 0,
                'annotation_index' => $annotationIndex,
                'annotation' => $annotation,
            ])];
        }

        $item = $this->openItems[$index];
        $item['annotations'][] = $annotation;
        $this->openItems[$index] = $item;

        return [$this->frame('response.output_text.annotation.added', [
            'item_id' => $item['itemId'],
            'output_index' => $item['outputIndex'],
            'content_index' => 0,
            'annotation_index' => \count($item['annotations']) - 1,
            'annotation' => $annotation,
        ])];
    }

    /**
     * Appends an annotation to a settled message item's output_text part.
     *
     * @param array<string, mixed> $annotation
     */
    private function appendSettledAnnotation(int $settledKey, array $annotation): int
    {
        $entry = $this->outputItems[$settledKey];
        $entry['content'][0]['annotations'][] = $annotation;
        $this->outputItems[$settledKey] = $entry;

        return \count($entry['content'][0]['annotations']) - 1;
    }

    /**
     * The index of the open message item citations attach to: the most recently
     * opened message-kind item, or null when none is open.
     */
    private function openMessageIndex(): ?int
    {
        for ($key = array_key_last($this->openItems); null !== $key; --$key) {
            if ('message' === $this->openItems[$key]['kind']) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function handleProviderToolEvent(HawkiProviderToolEvent $event): array
    {
        if (!$this->emitCustomEvents) {
            return [];
        }

        return [$this->frame('hawki:provider_tool_event', [
            'item_id' => $event->itemId,
            'event_type' => $event->type,
            'data' => $event->data,
            'status' => $event->status,
        ])];
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function bufferUsage(UsageEvent $event): array
    {
        $this->pendingUsage = $event->usage;

        return [];
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function bufferFinish(FinishEvent $event): array
    {
        $this->pendingFinish = $event->finishReason;

        return [];
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function openMessageItem(int $index): array
    {
        $itemId = 'msg_' . Str::uuid()->toString();
        $outputIndex = \count($this->outputItems) + \count($this->openItems);

        $this->openItems[$index] = [
            'kind' => 'message',
            'itemId' => $itemId,
            'outputIndex' => $outputIndex,
            'text' => '',
            'annotations' => [],
            'callId' => '',
            'name' => '',
            'arguments' => '',
        ];

        return [
            $this->frame('response.output_item.added', [
                'output_index' => $outputIndex,
                'item' => [
                    'id' => $itemId,
                    'type' => 'message',
                    'role' => 'assistant',
                    'status' => 'in_progress',
                    'content' => [],
                ],
            ]),
            $this->frame('response.content_part.added', [
                'item_id' => $itemId,
                'output_index' => $outputIndex,
                'content_index' => 0,
                'part' => ['type' => 'output_text', 'text' => '', 'annotations' => []],
            ]),
        ];
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function openReasoningItem(int $index): array
    {
        $itemId = 'rs_' . Str::uuid()->toString();
        $outputIndex = \count($this->outputItems) + \count($this->openItems);

        $this->openItems[$index] = [
            'kind' => 'reasoning',
            'itemId' => $itemId,
            'outputIndex' => $outputIndex,
            'text' => '',
            'annotations' => [],
            'callId' => '',
            'name' => '',
            'arguments' => '',
        ];

        return [
            $this->frame('response.output_item.added', [
                'output_index' => $outputIndex,
                'item' => [
                    'id' => $itemId,
                    'type' => 'reasoning',
                ],
            ]),
            $this->frame('response.reasoning_summary_part.added', [
                'item_id' => $itemId,
                'output_index' => $outputIndex,
                'summary_index' => 0,
                'part' => ['type' => 'summary_text', 'text' => ''],
            ]),
        ];
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function openFunctionCallItem(int $index, string $callId, string $name): array
    {
        $itemId = 'fc_' . Str::uuid()->toString();
        $outputIndex = \count($this->outputItems) + \count($this->openItems);

        $this->openItems[$index] = [
            'kind' => 'function_call',
            'itemId' => $itemId,
            'outputIndex' => $outputIndex,
            'text' => '',
            'annotations' => [],
            'callId' => $callId,
            'name' => $name,
            'arguments' => '',
        ];

        return [
            $this->frame('response.output_item.added', [
                'output_index' => $outputIndex,
                'item' => [
                    'id' => $itemId,
                    'type' => 'function_call',
                    'call_id' => $callId,
                    'name' => $name,
                    'arguments' => '',
                    'status' => 'in_progress',
                ],
            ]),
        ];
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function closeItem(int $index): array
    {
        $item = $this->openItems[$index];
        unset($this->openItems[$index]);

        $frames = [];
        $outputIndex = $item['outputIndex'];

        if ('message' === $item['kind']) {
            $annotations = $item['annotations'];

            $frames[] = $this->frame('response.output_text.done', [
                'item_id' => $item['itemId'],
                'output_index' => $outputIndex,
                'content_index' => 0,
                'text' => $item['text'],
            ]);

            $frames[] = $this->frame('response.content_part.done', [
                'item_id' => $item['itemId'],
                'output_index' => $outputIndex,
                'content_index' => 0,
                'part' => ['type' => 'output_text', 'text' => $item['text'], 'annotations' => $annotations],
            ]);

            $frames[] = $this->frame('response.output_item.done', [
                'output_index' => $outputIndex,
                'item' => [
                    'id' => $item['itemId'],
                    'type' => 'message',
                    'role' => 'assistant',
                    'status' => 'completed',
                    'content' => [['type' => 'output_text', 'text' => $item['text'], 'annotations' => $annotations]],
                ],
            ]);

            $this->outputItems[] = [
                'id' => $item['itemId'],
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [['type' => 'output_text', 'text' => $item['text'], 'annotations' => $annotations]],
            ];

            return $frames;
        }

        if ('reasoning' === $item['kind']) {
            $frames[] = $this->frame('response.reasoning_summary_text.done', [
                'item_id' => $item['itemId'],
                'output_index' => $outputIndex,
                'summary_index' => 0,
                'text' => $item['text'],
            ]);

            $frames[] = $this->frame('response.reasoning_summary_part.done', [
                'item_id' => $item['itemId'],
                'output_index' => $outputIndex,
                'summary_index' => 0,
                'part' => ['type' => 'summary_text', 'text' => $item['text']],
            ]);

            $frames[] = $this->frame('response.output_item.done', [
                'output_index' => $outputIndex,
                'item' => [
                    'id' => $item['itemId'],
                    'type' => 'reasoning',
                    'summary' => '' !== $item['text']
                        ? [['type' => 'summary_text', 'text' => $item['text']]]
                        : [],
                ],
            ]);

            $this->outputItems[] = [
                'id' => $item['itemId'],
                'type' => 'reasoning',
                'summary' => '' !== $item['text']
                    ? [['type' => 'summary_text', 'text' => $item['text']]]
                    : [],
            ];

            return $frames;
        }

        $arguments = '' === $item['arguments'] ? '{}' : $item['arguments'];

        $frames[] = $this->frame('response.function_call_arguments.done', [
            'item_id' => $item['itemId'],
            'output_index' => $outputIndex,
            'arguments' => $arguments,
        ]);

        $frames[] = $this->frame('response.output_item.done', [
            'output_index' => $outputIndex,
            'item' => [
                'id' => $item['itemId'],
                'type' => 'function_call',
                'call_id' => $item['callId'],
                'name' => $item['name'],
                'arguments' => $arguments,
                'status' => 'completed',
            ],
        ]);

        $this->outputItems[] = [
            'id' => $item['itemId'],
            'type' => 'function_call',
            'call_id' => $item['callId'],
            'name' => $item['name'],
            'arguments' => $arguments,
            'status' => 'completed',
        ];

        return $frames;
    }

    /**
     * @return array<int, array{event: string, data: array<string, mixed>}>
     */
    private function closeOpenItems(): array
    {
        $frames = [];

        foreach (array_keys($this->openItems) as $index) {
            $frames = [...$frames, ...$this->closeItem($index)];
        }

        return $frames;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{event: string, data: array<string, mixed>}
     */
    private function frame(string $event, array $data): array
    {
        return [
            'event' => $event,
            'data' => ['type' => $event, 'sequence_number' => $this->sequenceNumber++, ...$data],
        ];
    }

    private static function ensureResponseIdPrefix(string $id): string
    {
        return str_starts_with($id, 'resp_') ? $id : 'resp_' . $id;
    }
}
