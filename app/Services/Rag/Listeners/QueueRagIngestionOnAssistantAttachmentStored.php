<?php

declare(strict_types=1);

namespace App\Services\Rag\Listeners;

use App\Jobs\IngestAttachmentToRag;
use App\Services\Assistant\Events\AssistantAttachmentStoredEvent;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use App\Services\Rag\Contracts\RagIngesterInterface;
use App\Services\Rag\Values\RagIngestionStatus;
use Illuminate\Container\Attributes\Config;
use Psr\Log\LoggerInterface;

/**
 * Reacts to a newly stored assistant knowledge file by pre-flight
 * provisioning the assistant's RAG dataset (check-then-create, best-effort)
 * and queueing the ingestion job.
 *
 * The pre-flight never blocks the upload: on any failure it only logs a
 * warning and leaves dataset provisioning to the queued job, which retries
 * {@see RagIngesterInterface::ensureDataset()} with backoff. A no-op while
 * RAG ingestion is disabled — the attachment then simply stays
 * rag_status = null ("not applicable").
 */
class QueueRagIngestionOnAssistantAttachmentStored
{
    public function __construct(
        private readonly RagIngesterInterface $ingester,
        private readonly AssistantAttachmentRepository $assistantAttachmentRepository,
        private readonly LoggerInterface $logger,
        #[Config('rag.enabled')]
        private readonly bool $enabled,
        #[Config('rag.dataset_prefix')]
        private readonly string $datasetPrefix,
    ) {
    }

    public function handle(AssistantAttachmentStoredEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $datasetId = $this->datasetPrefix . $event->assistant->id;

        try {
            if (!$this->ingester->ensureDataset($datasetId)) {
                $this->logger->warning(
                    'Pre-flight RAG dataset provisioning was rejected; the ingestion job will retry',
                    ['assistant_id' => $event->assistant->id, 'dataset_id' => $datasetId],
                );
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Pre-flight RAG dataset provisioning failed; the ingestion job will retry',
                ['exception' => $e, 'assistant_id' => $event->assistant->id, 'dataset_id' => $datasetId],
            );
        }

        $this->assistantAttachmentRepository->updateRagState(
            $event->assistantAttachment->id,
            RagIngestionStatus::PENDING,
        );

        IngestAttachmentToRag::dispatch(
            $event->assistant->id,
            $event->assistantAttachment->id,
        );
    }
}
