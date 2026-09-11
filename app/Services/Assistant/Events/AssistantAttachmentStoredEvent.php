<?php

declare(strict_types=1);

namespace App\Services\Assistant\Events;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAttachment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a knowledge file was stored, extracted and attached to an
 * assistant. Downstream consumers (e.g. RAG ingestion) can react without the
 * assistant domain knowing about them.
 */
readonly class AssistantAttachmentStoredEvent
{
    use Dispatchable;

    public function __construct(
        public Assistant $assistant,
        public AssistantAttachment $assistantAttachment,
        public User $uploader,
    ) {
    }
}
