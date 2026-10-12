<?php

declare(strict_types=1);

namespace App\Services\AssistantKnowledge\Listeners;

use App\Services\Assistant\Events\AssistantAttachmentStoredEvent;
use App\Services\AssistantKnowledge\RagIngestionService;

/**
 * Deterministic upload workflow over the ingestion service: a newly stored
 * assistant knowledge file is queued for RAG ingestion. The WHEN lives
 * here (event-driven), the WHAT in {@see RagIngestionService::ingestAttachment()}.
 */
class QueueRagIngestionOnAssistantAttachmentStored
{
    public function __construct(
        private readonly RagIngestionService $ingestion,
    ) {
    }

    public function handle(AssistantAttachmentStoredEvent $event): void
    {
        $this->ingestion->ingestAttachment($event->assistantAttachment);
    }
}
