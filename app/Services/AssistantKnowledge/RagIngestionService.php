<?php

declare(strict_types=1);

namespace App\Services\AssistantKnowledge;

use App\Models\Assistants\AssistantAttachment;
use App\Services\AssistantKnowledge\Jobs\IngestAttachmentToRag;
use App\Services\AssistantKnowledge\Repositories\RagIngestionStateRepository;
use App\Services\Rag\Config\RagConfig;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Values\RagIngestionStatus;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Bus;
use Psr\Log\LoggerInterface;

/**
 * The ingestion side of the RAG module as one callable seam: provisions the
 * assistant's dataset, queues the ingestion job, and removes documents
 * again on de-registration.
 *
 * The module's listeners are thin deterministic workflows over this
 * service ("on upload → ingest", "on deletion → remove"); the WHEN lives
 * in those workflows, the WHAT lives here — a future caller (workflow
 * engine, plugin API) can drive ingestion deterministically through the
 * same seam without re-implementing the bookkeeping.
 *
 * All entry points are gated on the ingestion-side config flag and are
 * no-ops while it is disabled — the attachment then simply keeps
 * rag_status = null ("not applicable"). De-registration is best-effort by
 * design: a failing de-ingestion only logs a warning, because the
 * attachment deletion itself must never be blocked by the RAG server.
 * Orphaned vectors are cleaned up by clearing the dataset when the whole
 * assistant is removed.
 */
#[Singleton]
final class RagIngestionService
{
    public function __construct(
        private readonly RagIngesterInterface $ingester,
        private readonly RagIngestionStateRepository $ragState,
        private readonly RagConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Ingests a newly stored attachment: pre-flight provisioning of the
     * assistant's RAG dataset (check-then-create, best-effort), then the
     * queued ingestion job.
     *
     * The pre-flight never blocks the caller: on any failure it only logs
     * a warning and leaves dataset provisioning to the queued job, which
     * retries {@see RagIngesterInterface::ensureDataset()} with backoff.
     */
    public function ingestAttachment(AssistantAttachment $attachment): void
    {
        if (!$this->config->enabled) {
            return;
        }

        $assistantId = (int) $attachment->assistant_id;
        $datasetId = $this->config->datasetPrefix . $assistantId;

        try {
            if (!$this->ingester->ensureDataset($datasetId)) {
                $this->logger->warning(
                    'Pre-flight RAG dataset provisioning was rejected; the ingestion job will retry',
                    ['assistant_id' => $assistantId, 'dataset_id' => $datasetId],
                );
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Pre-flight RAG dataset provisioning failed; the ingestion job will retry',
                ['exception' => $e, 'assistant_id' => $assistantId, 'dataset_id' => $datasetId],
            );
        }

        $this->ragState->updateState(
            $attachment->id,
            RagIngestionStatus::PENDING,
        );

        // The job travels inside a batch so a still-queued run can be
        // revoked on attachment deletion (batch cancellation makes the
        // worker discard it without executing, regardless of the queue
        // driver). PENDING is written before dispatching so fast workers
        // — and the sync driver — see an active attachment; the batch id
        // follows as a second write. A crash in between only degrades
        // revocation: the job itself still no-ops on a vanished
        // attachment.
        $batch = Bus::batch([
            new IngestAttachmentToRag($assistantId, $attachment->id),
        ])
            ->name(\sprintf(
                'rag-ingestion-assistant-%d-attachment-%d',
                $assistantId,
                $attachment->id,
            ))
            ->dispatch();

        $this->ragState->updateState(
            $attachment->id,
            RagIngestionStatus::PENDING,
            batchId: $batch->id,
        );
    }

    /**
     * Removes an attachment's document from the assistant's RAG dataset.
     */
    public function removeAttachment(AssistantAttachment $attachment): void
    {
        if (!$this->config->enabled) {
            return;
        }

        if (null === $attachment->rag_status || !$attachment->rag_status->mayExistInRag()) {
            // Never ingested or skipped — nothing to remove.
            return;
        }

        try {
            // Both ingestion modes persist a server-assigned handle in
            // rag_document_id (`adoc_*` for file ingestions, `source_*`
            // for text ones); the uuid fallback only covers legacy text
            // rows ingested before handles were stored.
            $deleted = $this->ingester->deleteDocument(
                $this->config->datasetPrefix . (string) $attachment->assistant_id,
                $attachment->rag_document_id ?? $attachment->uuid,
            );

            if (!$deleted) {
                $this->logger->warning(
                    'RAG de-ingestion was rejected; orphaned vectors may remain in the dataset',
                    ['assistant_id' => $attachment->assistant_id, 'attachment_uuid' => $attachment->uuid],
                );
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'RAG de-ingestion failed; orphaned vectors may remain in the dataset',
                ['exception' => $e, 'assistant_id' => $attachment->assistant_id, 'attachment_uuid' => $attachment->uuid],
            );
        }
    }
}
