<?php

declare(strict_types=1);

namespace App\Services\Rag\Listeners;

use App\Services\Assistant\Events\AssistantAttachmentDeletingEvent;
use Illuminate\Support\Facades\Bus;
use Psr\Log\LoggerInterface;

/**
 * Revokes a still-queued RAG ingestion when its attachment is deleted.
 *
 * The ingestion job travels inside a batch (see
 * {@see QueueRagIngestionOnAssistantAttachmentStored}); cancelling that
 * batch makes the worker discard a queued run without executing it —
 * on any queue driver. Deliberately not gated on `rag.enabled`: a job
 * dispatched before the flag was switched off can still be queued.
 *
 * Best-effort by design: a failing cancellation is only logged as a
 * warning and never blocks the attachment deletion. The job's own
 * vanished-attachment check remains the final safety net, covering runs
 * already in flight and attachments whose batch id was never persisted.
 */
class CancelPendingRagIngestionOnAssistantAttachmentDeletion
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(AssistantAttachmentDeletingEvent $event): void
    {
        $batchId = $event->assistantAttachment->rag_batch_id;

        if (null === $batchId || '' === $batchId) {
            // Batch-less attachments (legacy rows or a crash between
            // dispatch and persist) have nothing to revoke.
            return;
        }

        try {
            Bus::findBatch($batchId)?->cancel();
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Cancelling the queued RAG ingestion failed; the job will still no-op on the deleted attachment',
                ['exception' => $e, 'rag_batch_id' => $batchId],
            );
        }
    }
}
