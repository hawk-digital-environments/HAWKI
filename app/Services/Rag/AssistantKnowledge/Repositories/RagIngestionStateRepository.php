<?php

declare(strict_types=1);

namespace App\Services\Rag\AssistantKnowledge\Repositories;

use App\Models\Assistants\AssistantAttachment;
use App\Services\Rag\Values\RagIngestionStatus;
use App\Services\Rag\Values\RagIngestionUserError;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;

/**
 * Advances the RAG ingestion state stored on the assistant attachment
 * rows (`rag_*` columns are RAG-owned state on an assistant-owned table).
 *
 * Lives in the Rag slice — not on the assistant attachment repository — so
 * the dependency direction stays Rag → Assistant (the assistant slice
 * knows nothing about RAG).
 */
#[UseModel(AssistantAttachment::class)]
class RagIngestionStateRepository extends AbstractRepository
{
    public function __construct(
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    /**
     * Sets the ingest timestamp automatically when the status becomes
     * INGESTED; passing null for taskId/documentId/batchId/error/userError
     * leaves the stored values untouched.
     */
    public function updateState(
        int $assistantAttachmentId,
        RagIngestionStatus $status,
        ?string $taskId = null,
        ?string $error = null,
        ?string $documentId = null,
        ?string $batchId = null,
        ?RagIngestionUserError $userError = null,
    ): void {
        $update = ['rag_status' => $status->value];

        if (null !== $taskId) {
            $update['rag_task_id'] = $taskId;
        }

        if (null !== $error) {
            $update['rag_error'] = $error;
        }

        if (null !== $userError) {
            $update['rag_user_error'] = $userError->value;
        }

        if (null !== $documentId) {
            $update['rag_document_id'] = $documentId;
        }

        if (null !== $batchId) {
            $update['rag_batch_id'] = $batchId;
        }

        if (RagIngestionStatus::INGESTED === $status) {
            $update['rag_ingested_at'] = $this->clock->now();
        }

        $this->getQuery()->whereKey($assistantAttachmentId)->update($update);
    }
}
