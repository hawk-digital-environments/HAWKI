<?php

declare(strict_types=1);

namespace App\Services\Rag\Citations;

use Closure;

/**
 * One decoded query-search tool result, the value-object boundary between
 * the MCP wire format and the citation listener: both wire shapes MCP
 * results come in (`structuredContent` and `content[].text` payloads) are
 * known here and nowhere else.
 *
 * Two read paths share the single decode:
 * - {@see withCiteIds()} returns a new instance with a run-stable
 *   `citeId` injected into every `documents` list entry (the model echoes
 *   these tokens per the system prompt's citation rule); the collector
 *   closure allocates them, keyed `kind:document_id` so a document's
 *   citeId agrees everywhere. {@see isRewritten()} tells whether anything
 *   changed, so an untouched result never needs re-encoding.
 * - {@see documentReferences()} derives the source references from the
 *   search hits' metadata — `metadata.external_document_id` (direct-text
 *   ingestions) and `metadata.document_id` (managed documents) — in result
 *   order, deduplicated.
 */
final class RagSearchToolResult
{
    /**
     * @param array<string, mixed> $data decoded tool result
     */
    private function __construct(
        public readonly array $data,
        private readonly bool $rewritten = false,
    ) {}

    public static function fromJson(string $json): ?self
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? new self($decoded) : null;
    }

    public function isRewritten(): bool
    {
        return $this->rewritten;
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR);
    }

    /**
     * @param Closure(string): string $citeIdFor allocates the cite id for a
     *                                             `kind:document_id` key
     */
    public function withCiteIds(Closure $citeIdFor): self
    {
        $data = $this->data;
        $rewritten = $this->rewritten;

        if (isset($data['structuredContent']) && is_array($data['structuredContent'])) {
            $payload = $data['structuredContent'];
            $rewritten = $this->injectCiteIds($payload, $citeIdFor) || $rewritten;
            $data['structuredContent'] = $payload;
        }

        foreach (is_array($data['content'] ?? null) ? $data['content'] : [] as $index => $content) {
            if (!is_array($content) || ($content['type'] ?? null) !== 'text' || !is_string($content['text'] ?? null)) {
                continue;
            }

            $payload = json_decode($content['text'], true);

            if (!is_array($payload)) {
                continue;
            }

            if ($this->injectCiteIds($payload, $citeIdFor)) {
                $data['content'][$index]['text'] = json_encode($payload, JSON_THROW_ON_ERROR);
                $rewritten = true;
            }
        }

        return new self($data, $rewritten);
    }

    /**
     * Appends one human-readable notice as an additional `content` text
     * entry — the terminal last-call signal the citation listener adds to
     * the final permitted knowledge-tool call of a run. The search payload
     * itself stays data-only (no instruction keys), keeping the result
     * contract of the cite-id rewrite.
     */
    public function withTerminalNotice(string $notice): self
    {
        $data = $this->data;
        $data['content'] = is_array($data['content'] ?? null) ? $data['content'] : [];
        $data['content'][] = ['type' => 'text', 'text' => $notice];

        return new self($data, true);
    }

    /**
     * @return list<RagDocumentReference>
     */
    public function documentReferences(): array
    {
        $references = [];
        $seen = [];

        foreach ($this->searchHits() as $hit) {
            if (!is_array($hit)) {
                continue;
            }

            $title = is_string(data_get($hit, 'metadata.title')) && trim((string) data_get($hit, 'metadata.title')) !== ''
                ? (string) data_get($hit, 'metadata.title')
                : null;

            $candidates = [
                ['attachments', data_get($hit, 'metadata.external_document_id')],
                ['documents', data_get($hit, 'metadata.document_id')],
            ];

            foreach ($candidates as [$kind, $id]) {
                if (!is_string($id) || trim($id) === '' || isset($seen[$kind . ':' . $id])) {
                    continue;
                }

                $seen[$kind . ':' . $id] = true;

                $references[] = new RagDocumentReference(
                    kind: $kind,
                    id: $id,
                    name: $title ?? $id,
                );
            }
        }

        return $references;
    }

    /**
     * Mutates one decoded payload's `documents` list in place (array
     * internals stay private): every well-formed entry gains its cite id
     * unless it already carries the right one. Returns whether anything
     * changed.
     *
     * @param array<string, mixed> $payload
     * @param Closure(string): string $citeIdFor
     */
    private function injectCiteIds(array &$payload, Closure $citeIdFor): bool
    {
        $rewritten = false;

        foreach (is_array($payload['documents'] ?? null) ? $payload['documents'] : [] as $index => $document) {
            if (!is_array($document) || !is_string($document['kind'] ?? null) || !is_string($document['document_id'] ?? null)) {
                continue;
            }

            $citeId = $citeIdFor($document['kind'] . ':' . $document['document_id']);

            if (($document['citeId'] ?? null) !== $citeId) {
                $payload['documents'][$index]['citeId'] = $citeId;
                $rewritten = true;
            }
        }

        return $rewritten;
    }

    /**
     * The search hits of the result — `structuredContent.response.results`,
     * falling back to the first decodable `content[].text` payload's hits.
     *
     * @return list<mixed>
     */
    private function searchHits(): array
    {
        $results = data_get($this->data, 'structuredContent.response.results');

        if (is_array($results)) {
            return $results;
        }

        foreach (is_array($this->data['content'] ?? null) ? $this->data['content'] : [] as $content) {
            if (!is_array($content) || ($content['type'] ?? null) !== 'text' || !is_string($content['text'] ?? null)) {
                continue;
            }

            $decoded = json_decode($content['text'], true);

            if (is_array($decoded)) {
                $hits = data_get($decoded, 'response.results');

                return is_array($hits) ? $hits : [];
            }
        }

        return [];
    }
}
