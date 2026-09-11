<?php

declare(strict_types=1);

namespace App\Services\Assistant\Events;

use App\Models\Assistants\AssistantAttachment;

/**
 * Event fired before an assistant attachment is deleted.
 * Listeners can use this event to perform cleanup tasks, such as deleting
 * the associated stored file or removing the document from the RAG dataset.
 */
readonly class AssistantAttachmentDeletingEvent
{
    public function __construct(
        public AssistantAttachment $assistantAttachment
    ) {
    }
}
