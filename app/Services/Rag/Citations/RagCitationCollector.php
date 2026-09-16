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
 * Registered as a scoped container binding: one instance per request shared
 * between writer and reader.
 */
class RagCitationCollector
{
    /** @var array<string, RagDocumentCitation> */
    private array $citationsByReference = [];

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
     * Returns all collected citations and resets the collector — draining
     * is one-shot, so retries and repeated reads start clean.
     *
     * @return list<RagDocumentCitation>
     */
    public function drain(): array
    {
        $citations = array_values($this->citationsByReference);

        $this->citationsByReference = [];

        return $citations;
    }
}
