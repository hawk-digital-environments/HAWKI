<?php

declare(strict_types=1);

namespace App\Services\AssistantKnowledge\Listeners;

use App\Models\Assistants\AssistantAttachment;
use App\Services\Ai\Tools\LaravelAi\Events\McpToolCalledFilterEvent;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Citations\RagDocumentCitation;
use App\Services\Rag\Citations\RagDocumentReference;
use App\Services\Rag\Config\RagConfig;
use App\Services\Storage\Values\StoredFileIdentifier;
use Psr\Log\LoggerInterface;

/**
 * Maps the document references in a finished RAG MCP tool call onto locally
 * stored assistant attachments and collects them as document citations for
 * the streaming frontend.
 *
 * References come from the search hits' metadata — `metadata.external_document_id`
 * (the caller-owned document id of direct-text ingestions; HAWKI uses the
 * attachment uuid, so it resolves as the attachment table's own uuids) and
 * `metadata.document_id` (managed documents, resolved through the
 * ingestion-time `rag_document_id` mapping).
 *
 * The same pass rewrites the tool result before the model reads it: every
 * entry of the result's `documents` list gains a run-stable `citeId`
 * (`D1`, `D2`, … — same document keeps its citeId across all searches of
 * the run), and the RAG server's cite-by-name instruction line is swapped
 * for the citeId form. The system prompt's citation rule then only asks
 * the model to echo a two-character token instead of copying a long
 * document name character-for-character — small models garble the latter.
 *
 * Serves exactly one tool: the configured knowledge query tool
 * ({@see RagConfig::$queryToolName}). Tool calls from anything else —
 * the web-search tool of the same server included — are a no-op and their
 * raw results pass through untouched.
 *
 * Citations with a matching attachment point at HAWKI's storage proxy (the
 * same authorized URL the chat uses for uploads); documents without one are
 * still cited, by name only.
 */
class CollectRagDocumentCitationsOnMcpToolCalled
{
    /** The RAG server's cite-by-name instruction, replaced with the citeId form. */
    private const CITE_BY_NAME_INSTRUCTION = 'Cite sources by the document names from the `documents` list.';

    private const CITE_ID_INSTRUCTION = 'Cite sources inline by each document\'s `citeId` from the `documents` list, e.g. [[D1]].';

    public function __construct(
        private readonly AssistantAttachmentRepository $attachments,
        private readonly RagCitationCollector $citations,
        private readonly RagConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(McpToolCalledFilterEvent $event): void
    {
        if ($event->getTool()->name !== $this->config->queryToolName) {
            return;
        }

        $this->rewriteCiteIds($event);

        foreach ($this->referencedDocuments($event->getResult()) as $reference) {
            $attachment = $this->resolveAttachment($reference);

            if ($attachment instanceof AssistantAttachment) {
                $title = trim((string) ($attachment->name ?? '')) !== '' ? (string) $attachment->name : $reference->name;
            } else {
                $this->logger->debug('RAG document citation without a local attachment', [
                    'kind' => $reference->kind,
                    'reference' => $reference->id,
                    'name' => $reference->name,
                ]);
                $title = $reference->name;
            }

            $this->citations->collect(
                $reference->kind . ':' . $reference->id,
                new RagDocumentCitation(
                    url: $attachment instanceof AssistantAttachment ? $this->attachmentUrl($attachment) : '',
                    title: $title,
                    citeId: $this->citations->citeIdFor($reference->kind . ':' . $reference->id),
                ),
            );
        }
    }

    /**
     * Adds run-stable cite ids to every `documents` list entry of the tool
     * result and swaps the cite-by-name instruction line, so the model sees
     * and echoes `citeId` tokens. Rewrites both wire shapes MCP results
     * come in: `structuredContent` and `content[].text` payloads. A no-op
     * (result untouched) when the result carries neither documents nor the
     * instruction line.
     */
    private function rewriteCiteIds(McpToolCalledFilterEvent $event): void
    {
        $result = json_decode($event->getResult(), true);

        if (!is_array($result)) {
            return;
        }

        $rewritten = is_array($result['structuredContent'] ?? null)
            && $this->rewritePayload($result['structuredContent']);

        foreach (is_array($result['content'] ?? null) ? $result['content'] : [] as $index => $content) {
            if (!is_array($content) || ($content['type'] ?? null) !== 'text' || !is_string($content['text'] ?? null)) {
                continue;
            }

            $decoded = json_decode($content['text'], true);

            if (is_array($decoded) && $this->rewritePayload($decoded)) {
                $result['content'][$index]['text'] = json_encode($decoded, JSON_THROW_ON_ERROR);
                $rewritten = true;
            }
        }

        if ($rewritten) {
            $event->setResult(json_encode($result, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Mutates one decoded payload in place: cite ids onto `documents`
     * entries (keyed `kind:document_id`, the same keys the citation
     * collection uses, so a document's citeId agrees everywhere) and the
     * instruction swap.
     *
     * @param array<string, mixed> $payload
     */
    private function rewritePayload(array &$payload): bool
    {
        $rewritten = false;

        foreach (is_array($payload['documents'] ?? null) ? $payload['documents'] : [] as $index => $document) {
            if (!is_array($document) || !is_string($document['kind'] ?? null) || !is_string($document['document_id'] ?? null)) {
                continue;
            }

            $citeId = $this->citations->citeIdFor($document['kind'] . ':' . $document['document_id']);

            if (($document['citeId'] ?? null) !== $citeId) {
                $payload['documents'][$index]['citeId'] = $citeId;
                $rewritten = true;
            }
        }

        if (is_string($payload['instructions'] ?? null)
            && str_contains($payload['instructions'], self::CITE_BY_NAME_INSTRUCTION)) {
            $payload['instructions'] = str_replace(
                self::CITE_BY_NAME_INSTRUCTION,
                self::CITE_ID_INSTRUCTION,
                $payload['instructions'],
            );
            $rewritten = true;
        }

        return $rewritten;
    }

    /**
     * Whether the MCP server with this label is declared in
     * `tools.mcp_servers` — the servers whose documents have local
     * counterparts and therefore get document-to-attachment mapping.
     */
    /**
     * The source references carried by the tool result, in result order.
     * Derived from the search hits' metadata — `metadata.external_document_id`
     * (direct-text ingestions) and `metadata.document_id` (managed
     * documents) — the same convention the RAG tool uses for its
     * `documents` list, which serves as the fallback source.
     *
     * @return list<RagDocumentReference>
     */
    private function referencedDocuments(string $resultJson): array
    {
        $result = json_decode($resultJson, true);

        if (!is_array($result)) {
            return [];
        }

        $references = [];
        $seen = [];

        $results = is_array(data_get($result, 'structuredContent.response.results'))
            ? data_get($result, 'structuredContent.response.results')
            : $this->resultsFromTextContent($result);

        foreach (is_array($results) ? $results : [] as $hit) {
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
     * @param array<string, mixed> $result
     *
     * @return mixed
     */
    private function resultsFromTextContent(array $result)
    {
        foreach (is_array($result['content'] ?? null) ? $result['content'] : [] as $content) {
            if (!is_array($content) || ($content['type'] ?? null) !== 'text' || !is_string($content['text'] ?? null)) {
                continue;
            }

            $decoded = json_decode($content['text'], true);

            if (is_array($decoded)) {
                return data_get($decoded, 'response.results');
            }
        }

        return null;
    }

    /**
     * Managed document ids resolve through the ingestion-time rag_document_id
     * mapping; attachment UUIDs are the attachment table's own uuids.
     */
    private function resolveAttachment(RagDocumentReference $reference): ?AssistantAttachment
    {
        return $reference->kind === 'attachments'
            ? $this->attachments->findOneByUuid($reference->id)
            : $this->attachments->findOneByRagDocumentId($reference->id);
    }

    /**
     * The storage-proxy URL serving the attachment's original file — the
     * same URL shape the chat frontend builds for uploaded attachments.
     */
    private function attachmentUrl(AssistantAttachment $attachment): string
    {
        $identifier = StoredFileIdentifier::fromAssistantAttachment($attachment);

        return url('/api/hawki/v1/proxy/storage/' . rawurlencode((string) $identifier));
    }
}
