<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat;

use App\Services\Ai\Chat\Exceptions\ChatStreamFailedException;
use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\CitationData;
use App\Services\Ai\Chat\Values\FinishReason;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Parts\CitationPart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Parts\ToolCallPart;
use App\Services\Ai\Chat\Values\Parts\UrlCitation;
use App\Services\Ai\Chat\Values\Stream\AiStreamEvent;
use App\Services\Ai\Chat\Values\Stream\ContentBlockEndEvent;
use App\Services\Ai\Chat\Values\Stream\ContentBlockStartEvent;
use App\Services\Ai\Chat\Values\Stream\FinishEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiCitationEvent;
use App\Services\Ai\Chat\Values\Stream\HawkiProviderToolEvent;
use App\Services\Ai\Chat\Values\Stream\ReasoningDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\StreamEndEvent;
use App\Services\Ai\Chat\Values\Stream\StreamStartEvent;
use App\Services\Ai\Chat\Values\Stream\TextDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallDeltaEvent;
use App\Services\Ai\Chat\Values\Stream\ToolCallStartEvent;
use App\Services\Ai\Chat\Values\Stream\UsageEvent;
use App\Services\Ai\Chat\Values\UsageInfo;
use App\Services\ExternalContent\CitationUrlCleaner;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason as VendorFinishReason;
use Laravel\Ai\Responses\Data\ToolCall as VendorToolCall;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\ReasoningEnd;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Psr\Clock\ClockInterface;

/**
 * Bridges the vendor stream event domain ({@see \Laravel\Ai\Streaming\Events\*}) and the
 * IR stream event domain ({@see AiStreamEvent}) — plus the stateless response
 * normalization ({@see normalizeResponse()}) for the non-streaming path.
 *
 * Normalizing a stream is inherently stateful (block indices, tool-call counters), so
 * this class is **not** a singleton: a fresh instance is created per invocation via
 * {@see AiStreamNormalizerFactory}.
 *
 * The {@see CitationUrlCleaner} runs here — inside the normalizer, before any formatter
 * sees the event — so every wire format benefits from tracking-parameter stripping.
 */
class AiStreamNormalizer
{
    private int $nextBlockIndex = 0;
    private int $nextToolCallIndex = 0;

    /**
     * @var array<string, int>
     */
    private array $blockIndexByVendorId = [];

    public function __construct(
        private readonly CitationUrlCleaner $citationCleaner,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Normalizes a vendor stream into IR stream events.
     *
     * @param iterable<\Laravel\Ai\Streaming\Events\StreamEvent> $vendorEvents
     *
     * @throws ChatStreamFailedException when the vendor stream reports an error event
     *
     * @return \Generator<int, AiStreamEvent>
     */
    public function normalizeStream(iterable $vendorEvents): \Generator
    {
        foreach ($vendorEvents as $vendorEvent) {
            $events = match (true) {
                $vendorEvent instanceof StreamStart => [new StreamStartEvent(
                    responseId: $vendorEvent->id,
                    model: $vendorEvent->model,
                    created: $vendorEvent->timestamp,
                )],
                $vendorEvent instanceof TextStart => [new ContentBlockStartEvent(
                    blockIndex: $this->allocateBlockIndex($vendorEvent->messageId),
                    blockType: ContentBlockStartEvent::BLOCK_TYPE_TEXT,
                )],
                $vendorEvent instanceof TextDelta => [new TextDeltaEvent(
                    text: $vendorEvent->delta,
                    blockIndex: $this->blockIndexFor($vendorEvent->messageId),
                )],
                $vendorEvent instanceof TextEnd => [new ContentBlockEndEvent(blockIndex: $this->blockIndexFor($vendorEvent->messageId))],
                $vendorEvent instanceof ReasoningStart => [new ContentBlockStartEvent(
                    blockIndex: $this->allocateBlockIndex($vendorEvent->reasoningId),
                    blockType: ContentBlockStartEvent::BLOCK_TYPE_THINKING,
                )],
                $vendorEvent instanceof ReasoningDelta => [new ReasoningDeltaEvent(
                    reasoning: $vendorEvent->delta,
                    blockIndex: $this->blockIndexFor($vendorEvent->reasoningId),
                )],
                $vendorEvent instanceof ReasoningEnd => [new ContentBlockEndEvent(blockIndex: $this->blockIndexFor($vendorEvent->reasoningId))],
                $vendorEvent instanceof ToolCall => $this->normalizeToolCall($vendorEvent->toolCall),
                $vendorEvent instanceof ToolResult => [],
                $vendorEvent instanceof ToolApprovalRequest => [new HawkiProviderToolEvent(
                    itemId: '',
                    type: 'approval_request',
                    data: $vendorEvent->pendingApprovals->toArray(),
                    status: 'pending',
                )],
                $vendorEvent instanceof Citation => [$this->normalizeCitation($vendorEvent)],
                $vendorEvent instanceof ProviderToolEvent => [new HawkiProviderToolEvent(
                    itemId: $vendorEvent->itemId,
                    type: $vendorEvent->type,
                    data: $vendorEvent->data,
                    status: $vendorEvent->status,
                )],
                $vendorEvent instanceof StreamEnd => $this->normalizeStreamEnd($vendorEvent),
                $vendorEvent instanceof Error => throw ChatStreamFailedException::fromVendorError($vendorEvent),
                default => [],
            };

            foreach ($events as $event) {
                yield $event;
            }
        }
    }

    /**
     * Normalizes a synchronous vendor response into the response IR (stateless).
     */
    public function normalizeResponse(AgentResponse $response): AiResponse
    {
        $parts = [];

        if ('' !== $response->text) {
            $parts[] = TextPart::from($response->text);
        }

        foreach ($response->toolCalls as $toolCall) {
            $parts[] = new ToolCallPart(
                toolCallId: $toolCall->id,
                toolName: $toolCall->name,
                toolInput: $toolCall->arguments,
            );
        }

        foreach ($this->citationCleaner->cleanMany($response->meta->citations->all()) as $citation) {
            if ($citation instanceof \Laravel\Ai\Responses\Data\UrlCitation) {
                $parts[] = new CitationPart(urlCitation: new UrlCitation(
                    startIndex: $citation->startIndex,
                    endIndex: $citation->endIndex,
                    title: $citation->title,
                    url: $citation->url,
                ));
            }
        }

        $usage = UsageInfo::fromLaravelUsage($response->usage);

        return new AiResponse(
            id: '' !== $response->invocationId ? $response->invocationId : 'resp_' . $this->clock->now()->getTimestamp(),
            model: $response->meta->model ?? '',
            created: $this->clock->now()->getTimestamp(),
            message: new AssistantMessage(parts: $parts),
            finishReason: $this->mapFinishReason($response->steps->last() === null ? VendorFinishReason::Stop : $response->steps->last()->finishReason),
            usage: $usage,
        );
    }

    /**
     * @return array<int, AiStreamEvent>
     */
    private function normalizeToolCall(VendorToolCall $toolCall): array
    {
        $toolCallIndex = $this->nextToolCallIndex++;
        $blockIndex = $this->nextBlockIndex++;

        return [
            new ToolCallStartEvent(
                toolCallId: $toolCall->id,
                toolName: $toolCall->name,
                toolCallIndex: $toolCallIndex,
                blockIndex: $blockIndex,
            ),
            new ToolCallDeltaEvent(
                toolCallId: $toolCall->id,
                argumentsDelta: (string) json_encode($toolCall->arguments, \JSON_UNESCAPED_UNICODE),
                toolCallIndex: $toolCallIndex,
                blockIndex: $blockIndex,
            ),
        ];
    }

    private function normalizeCitation(Citation $vendorEvent): HawkiCitationEvent
    {
        $citation = $vendorEvent->citation;

        if ($citation instanceof \Laravel\Ai\Responses\Data\UrlCitation) {
            $cleaned = $this->citationCleaner->clean($citation);

            return new HawkiCitationEvent(new CitationData(
                url: $cleaned instanceof \Laravel\Ai\Responses\Data\UrlCitation ? $cleaned->url : $citation->url,
                title: $citation->title,
                startIndex: $citation->startIndex,
                endIndex: $citation->endIndex,
            ));
        }

        return new HawkiCitationEvent(new CitationData(title: $citation->title));
    }

    /**
     * @return array<int, AiStreamEvent>
     */
    private function normalizeStreamEnd(StreamEnd $vendorEvent): array
    {
        return [
            new UsageEvent(UsageInfo::fromLaravelUsage($vendorEvent->usage)),
            new FinishEvent($this->mapFinishReason(VendorFinishReason::tryFrom($vendorEvent->reason) ?? VendorFinishReason::Stop)),
            new StreamEndEvent(),
        ];
    }

    private function mapFinishReason(VendorFinishReason $reason): FinishReason
    {
        return new FinishReason(match ($reason) {
            VendorFinishReason::Stop, VendorFinishReason::Unknown => FinishReasonType::STOP,
            VendorFinishReason::ToolCalls, VendorFinishReason::Continue => FinishReasonType::TOOL_CALLS,
            VendorFinishReason::Length => FinishReasonType::LENGTH,
            VendorFinishReason::ContentFilter => FinishReasonType::CONTENT_FILTER,
            VendorFinishReason::Error => FinishReasonType::ERROR,
        });
    }

    private function allocateBlockIndex(string $vendorId): int
    {
        return $this->blockIndexByVendorId[$vendorId] ??= $this->nextBlockIndex++;
    }

    private function blockIndexFor(string $vendorId): ?int
    {
        return $this->blockIndexByVendorId[$vendorId] ?? null;
    }
}
