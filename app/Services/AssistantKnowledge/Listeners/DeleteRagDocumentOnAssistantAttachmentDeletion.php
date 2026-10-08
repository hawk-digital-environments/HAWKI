<?php

declare(strict_types=1);

namespace App\Services\AssistantKnowledge\Listeners;

use App\Services\Assistant\Events\AssistantAttachmentDeletingEvent;
use App\Services\AssistantKnowledge\RagIngestionService;

/**
 * Deterministic deletion workflow over the ingestion service: an
 * attachment's document leaves the assistant's RAG dataset when the
 * attachment is deleted. The WHEN lives here (event-driven), the WHAT in
 * {@see RagIngestionService::removeAttachment()}.
 */
class DeleteRagDocumentOnAssistantAttachmentDeletion
{
    public function __construct(
        private readonly RagIngestionService $ingestion,
    ) {
    }

    public function handle(AssistantAttachmentDeletingEvent $event): void
    {
        $this->ingestion->removeAttachment($event->assistantAttachment);
    }
}
