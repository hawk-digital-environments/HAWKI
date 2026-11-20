<?php

declare(strict_types=1);

namespace App\Services\Rag\Citations;

/**
 * Collects knowledge-base document citations while MCP tools execute during
 * one agent run. The MCP tool-call listener writes; the agent adapter drains
 * once the run's tools have finished — streaming runs turn the drained
 * citations into `Citation` stream events, synchronous runs merge them into
 * the response meta.
 *
 * The same per-run state also tracks the knowledge tool's usage for the
 * terminal last-call signal: the AssistantKnowledge run listeners publish
 * the model's tool-round limit
 * ({@see \App\Services\Ai\Models\Settings\Values\AiModelSettings::getMaxToolCallingRounds()}
 * — non-streaming on AgentSendingEvent, streaming override on
 * AgentStreamInitiatedEvent) via {@see setToolRoundLimit()}, the MCP
 * tool-call listener counts the knowledge tool's search calls and flags the
 * final permitted one — everything resets together on {@see drain()}.
 *
 * Registered as a scoped container binding: one instance per request shared
 * between writer and reader.
 */
class RagCitationCollector
{
    /** @var array<string, RagDocumentCitation> */
    private array $citationsByReference = [];

    /** @var array<string, string> */
    private array $citeIdsByReference = [];

    private int $nextCiteId = 1;

    private int $searchToolCalls = 0;

    private int $toolRoundLimit = 0;

    /**
     * Records one document citation, keyed (and de-duplicated) by its
     * source reference (kind + managed document id or attachment uuid).
     */
    public function collect(string $reference, RagDocumentCitation $citation): void
    {
        if ($reference === '' || isset($this->citationsByReference[$reference])) {
            return;
        }

        $this->citationsByReference[$reference] = $citation;
    }

    /**
     * The stable cite id (`D1`, `D2`, …) for a source reference, assigned
     * in first-seen order across all tool calls of the run: a document
     * keeps its cite id across searches, so the model can echo it
     * character-for-character instead of copying a long document name.
     */
    public function citeIdFor(string $reference): string
    {
        if (isset($this->citeIdsByReference[$reference])) {
            return $this->citeIdsByReference[$reference];
        }

        return $this->citeIdsByReference[$reference] = 'D' . $this->nextCiteId++;
    }

    /**
     * Counts one knowledge-tool search call and returns its 1-based number
     * within the run — the caller detects the final permitted call with it.
     */
    public function recordSearchToolCall(): int
    {
        return ++$this->searchToolCalls;
    }

    /**
     * Publishes the run's tool-round limit, resolved by the agent adapter
     * from the model's settings (streaming and non-streaming differ); the
     * citation listener reads it via {@see toolRoundLimit()} to signal the
     * final permitted knowledge-tool call. 0 (or unset) disables the signal.
     */
    public function setToolRoundLimit(int $limit): void
    {
        $this->toolRoundLimit = $limit;
    }

    public function toolRoundLimit(): int
    {
        return $this->toolRoundLimit;
    }

    /**
     * Returns all collected citations and resets the collector — draining
     * is one-shot, so retries and repeated reads start clean (cite id
     * assignment, the search-call count and the tool-round limit reset
     * with them).
     *
     * @return list<RagDocumentCitation>
     */
    public function drain(): array
    {
        $citations = array_values($this->citationsByReference);

        $this->citationsByReference = [];
        $this->citeIdsByReference = [];
        $this->nextCiteId = 1;
        $this->searchToolCalls = 0;
        $this->toolRoundLimit = 0;

        return $citations;
    }
}
