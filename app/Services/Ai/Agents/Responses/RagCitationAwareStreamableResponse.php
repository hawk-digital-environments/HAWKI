<?php

declare(strict_types=1);

namespace App\Services\Ai\Agents\Responses;

use Closure;
use Generator;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\Citation as CitationEvent;
use Laravel\Ai\Streaming\Events\StreamEnd;

/**
 * A streaming agent response that surfaces RAG document citations as
 * regular {@see CitationEvent} stream chunks, so consumers (StreamController
 * and friends) receive them exactly like provider citations — no special
 * casing anywhere downstream.
 *
 * The inner response's final {@see StreamEnd} event is held back: by the
 * time it is emitted every tool execution of the run has finished, so
 * draining the citation collector at that point captures every document the
 * run touched. The citations are yielded as events before StreamEnd, then
 * StreamEnd is released. Everything else (text accumulation, usage, then
 * callbacks, conversation adoption) is inherited from the inner response's
 * own iteration.
 */
final class RagCitationAwareStreamableResponse extends StreamableAgentResponse
{
    public function __construct(
        private readonly StreamableAgentResponse $inner,
        private readonly Closure $drainCitations,
    ) {
        parent::__construct(
            invocationId: $inner->invocationId,
            generator: function (): Generator {
                yield from $this->innerEventsWithDocumentCitations();
            },
            meta: $inner->meta ?? new Meta,
        );
    }

    private function innerEventsWithDocumentCitations(): Generator
    {
        $streamEnd = null;

        foreach ($this->inner as $event) {
            if ($event instanceof StreamEnd) {
                $streamEnd = $event;

                continue;
            }

            yield $event;
        }

        foreach (($this->drainCitations)() as $citation) {
            yield new CitationEvent(
                id: 'rag_citation_' . uniqid(more_entropy: true),
                messageId: $this->inner->invocationId,
                citation: $citation,
                timestamp: (int) floor(microtime(true) * 1000),
            );
        }

        if ($streamEnd !== null) {
            yield $streamEnd;
        }
    }
}
