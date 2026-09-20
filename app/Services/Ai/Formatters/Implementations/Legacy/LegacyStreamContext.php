<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Implementations\Legacy;

use App\Services\Ai\Chat\Values\CitationData;
use App\Services\Ai\Chat\Values\Stream\AiStreamEvent;
use App\Services\Ai\Chat\Values\Stream\ContentBlockStartEvent;
use App\Services\Ai\Chat\Values\Stream\FinishEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiCitationEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiProviderToolEvent;
use App\Services\Ai\Chat\Values\Stream\ReasoningDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\StreamEndEvent;
use App\Services\Ai\Chat\Values\Stream\StreamStartEvent;
use App\Services\Ai\Chat\Values\Stream\TextDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallStartEvent;
use App\Services\Ai\Chat\Values\Stream\UsageEvent;

/**
 * Stateful translator from IR stream events to the legacy NDJSON frames.
 *
 * Frame grammar (one JSON object per newline, consumed by `public/js`):
 * `header` (author/model), `message` deltas, `status` progress
 * (reasoning/reasoning_delta/provider_tool_call/tool_call), `citation` frames
 * (batched until the stream ends, like the legacy controller), and the terminal
 * empty `message` + `completion` pair.
 */
class LegacyStreamContext
{
    private string $model = 'unknown';
    private string $text = '';

    /**
     * @var array<int, CitationData>
     */
    private array $pendingCitations = [];

    private bool $ended = false;

    /**
     * @param array{username: mixed, name: mixed, avatar_url: mixed} $author
     */
    public function __construct(
        private readonly array $author,
    ) {
    }

    /**
     * Translates one IR stream event into 0..n wire frames.
     *
     * @return array<int, array<string, mixed>>
     */
    public function transform(AiStreamEvent $event): array
    {
        return match (true) {
            $event instanceof StreamStartEvent => $this->handleStreamStart($event),
            $event instanceof TextDeltaEvent => $this->handleTextDelta($event),
            $event instanceof ContentBlockStartEvent => ContentBlockStartEvent::BLOCK_TYPE_THINKING === $event->blockType
                ? [$this->statusFrame('reasoning')]
                : [],
            $event instanceof ReasoningDeltaEvent => [$this->statusFrame('reasoning_delta', $event->reasoning)],
            $event instanceof HawkiProviderToolEvent => [$this->statusFrame('provider_tool_call', $event->type)],
            $event instanceof ToolCallStartEvent => [$this->statusFrame('tool_call', $event->toolName)],
            $event instanceof HawkiCitationEvent => $this->bufferCitation($event->citation),
            $event instanceof UsageEvent, $event instanceof FinishEvent => [],
            $event instanceof StreamEndEvent => [],
            default => [],
        };
    }

    /**
     * Flushes the buffered citations and the terminal completion pair.
     *
     * @return array<int, array<string, mixed>>
     */
    public function end(): array
    {
        if ($this->ended) {
            return [];
        }

        $this->ended = true;

        $frames = [];

        foreach ($this->pendingCitations as $citation) {
            $frames[] = $this->frame(type: 'citation', content: [
                'url' => $citation->url,
                'title' => $citation->title,
                'start_index' => $citation->startIndex,
                'end_index' => $citation->endIndex,
            ]);
        }

        $frames[] = $this->frame(type: 'message', content: '');
        $frames[] = $this->frame(type: 'completion', content: $this->text, isDone: true);

        return $frames;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function error(\Throwable $exception): array
    {
        $message = '' !== $exception->getMessage() ? $exception->getMessage() : 'There was an error while sending your request to the AI agent. Please try again later.';

        $this->ended = true;

        return [[
            'content' => $message,
            'type' => 'error',
            'isDone' => true,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleStreamStart(StreamStartEvent $event): array
    {
        $this->model = $event->model;

        return [
            array_merge($this->frame(type: 'header'), [
                'author' => $this->author,
                'model' => $this->model,
                'tools' => [],
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handleTextDelta(TextDeltaEvent $event): array
    {
        $this->text .= $event->text;

        return [$this->frame(type: 'message', content: $event->text)];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bufferCitation(CitationData $citation): array
    {
        $this->pendingCitations[] = $citation;

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusFrame(string $statusKey, mixed $value = ''): array
    {
        return $this->frame(type: 'status', content: '', status: ['key' => $statusKey, 'value' => $value]);
    }

    /**
     * @return array<string, mixed>
     */
    private function frame(string $type, mixed $content = '', bool $isDone = false, ?array $status = null): array
    {
        return [
            'isDone' => $isDone,
            'content' => $content,
            'type' => $type,
            'status' => $status,
        ];
    }
}
