<?php

declare(strict_types=1);

namespace App\Services\AssistantKnowledge\Listeners;

use App\Models\Assistants\AssistantAttachment;
use App\Services\Ai\Tools\LaravelAi\Events\McpToolCalledFilterEvent;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Citations\RagDocumentCitation;
use App\Services\Rag\Citations\RagDocumentReference;
use App\Services\Rag\Citations\RagSearchToolResult;
use App\Services\Rag\Config\RagConfig;
use App\Services\Storage\Values\StoredFileIdentifier;
use Psr\Log\LoggerInterface;

/**
 * Maps the document references in a finished RAG MCP tool call onto locally
 * stored assistant attachments and collects them as document citations for
 * the streaming frontend.
 *
 * The tool result is parsed through {@see RagSearchToolResult}: the same
 * decode feeds the cite-id injection (every `documents` list entry gains a
 * run-stable `citeId` — `D1`, `D2`, … — that the model echoes per the
 * system prompt's citation rule, see {@see \App\Services\AssistantKnowledge\Values\AssistantKnowledgePromptTemplate::KNOWLEDGE_TOOL})
 * and the reference extraction (`metadata.external_document_id` for
 * direct-text ingestions — HAWKI uses the attachment uuid — and
 * `metadata.document_id` for managed documents, resolved through the
 * ingestion-time `rag_document_id` mapping).
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

        $result = RagSearchToolResult::fromJson($event->getResult());

        if (null === $result) {
            return;
        }

        $result = $result->withCiteIds($this->citations->citeIdFor(...));

        if ($result->isRewritten()) {
            $event->setResult($result->toJson());
        }

        foreach ($result->documentReferences() as $reference) {
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
                $reference->key(),
                new RagDocumentCitation(
                    url: $attachment instanceof AssistantAttachment ? $this->attachmentUrl($attachment) : '',
                    title: $title,
                    citeId: $this->citations->citeIdFor($reference->key()),
                ),
            );
        }
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
