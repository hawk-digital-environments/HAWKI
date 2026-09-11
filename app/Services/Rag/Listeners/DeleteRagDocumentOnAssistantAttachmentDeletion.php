<?php

declare(strict_types=1);

namespace App\Services\Rag\Listeners;

use App\Services\Assistant\Events\AssistantAttachmentDeletingEvent;
use App\Services\Rag\Contracts\RagIngesterInterface;
use Illuminate\Container\Attributes\Config;
use Psr\Log\LoggerInterface;

/**
 * Removes an attachment's document from the assistant's RAG dataset when
 * the attachment is deleted.
 *
 * Best-effort by design: a failing de-ingestion is only logged as a warning
 * — the attachment deletion itself must never be blocked by the RAG server.
 * Orphaned vectors are cleaned up by clearing the dataset when the whole
 * assistant is removed.
 */
class DeleteRagDocumentOnAssistantAttachmentDeletion
{
    public function __construct(
        private readonly RagIngesterInterface $ingester,
        private readonly LoggerInterface $logger,
        #[Config('rag.enabled')]
        private readonly bool $enabled,
        #[Config('rag.dataset_prefix')]
        private readonly string $datasetPrefix,
    ) {
    }

    public function handle(AssistantAttachmentDeletingEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $assistantAttachment = $event->assistantAttachment;

        if (null === $assistantAttachment->rag_status || !$assistantAttachment->rag_status->mayExistInRag()) {
            // Never ingested or skipped — nothing to remove.
            return;
        }

        try {
            $deleted = $this->ingester->deleteDocument(
                $this->datasetPrefix . (string)$assistantAttachment->assistant_id,
                $assistantAttachment->uuid,
            );

            if (!$deleted) {
                $this->logger->warning(
                    'RAG de-ingestion was rejected; orphaned vectors may remain in the dataset',
                    ['assistant_id' => $assistantAttachment->assistant_id, 'attachment_uuid' => $assistantAttachment->uuid],
                );
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'RAG de-ingestion failed; orphaned vectors may remain in the dataset',
                ['exception' => $e, 'assistant_id' => $assistantAttachment->assistant_id, 'attachment_uuid' => $assistantAttachment->uuid],
            );
        }
    }
}
