<?php

declare(strict_types=1);

namespace App\Services\Rag\Listeners;

use App\Models\Assistants\AssistantAttachment;
use App\Services\Ai\Tools\LaravelAi\Events\McpToolCalledFilterEvent;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Citations\RagDocumentCitation;
use App\Services\Rag\Citations\RagDocumentReference;
use App\Services\Storage\Values\StoredFileIdentifier;
use Psr\Log\LoggerInterface;

/**
 * Maps the document references in a finished RAG MCP tool call onto locally
 * stored assistant attachments and collects them as document citations for
 * the streaming frontend.
 *
 * References come from the search hits' client metadata — `meta.attachment_uuid`
 * (direct-text ingestions, resolved as the attachment table's own uuids) and
 * `metadata.document_id` (managed documents, resolved through the
 * ingestion-time `rag_document_id` mapping) — the generic data plane between
 * ingestion and search.
 *
 * Gated per MCP server through `tools.mcp_servers.<key>.map_document_to_attachment`
 * (default true), matched by the tool's server label: only servers whose
 * documents have local counterparts (the HAWKI-RAG knowledge base, fed from
 * assistant attachments) are mapped. For other servers the listener is a
 * no-op and the raw result passes through untouched.
 *
 * Citations with a matching attachment point at HAWKI's storage proxy (the
 * same authorized URL the chat uses for uploads); documents without one are
 * still cited, by name only.
 */
class CollectRagDocumentCitationsOnMcpToolCalled
{
    public function __construct(
        private readonly AssistantAttachmentRepository $attachments,
        private readonly RagCitationCollector $citations,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(McpToolCalledFilterEvent $event): void
    {
        $server = $event->getTool()->server;

        if ($server === null || !$this->mapsDocumentsToAttachments($server->server_label)) {
            return;
        }

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
                ),
            );
        }
    }

    /**
     * Whether the config declares document-to-attachment mapping for the
     * MCP server with this label. Absent key defaults to true; servers not
     * present in the config are never mapped.
     */
    private function mapsDocumentsToAttachments(string $serverLabel): bool
    {
        foreach (config('tools.mcp_servers', []) as $server) {
            if (is_array($server) && ($server['server_label'] ?? null) === $serverLabel) {
                return (bool) ($server['map_document_to_attachment'] ?? true);
            }
        }

        return false;
    }

    /**
     * The source references carried by the tool result, in result order.
     * Derived from the search hits' client metadata — `meta.attachment_uuid`
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
                ['attachments', data_get($hit, 'meta.attachment_uuid')],
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
