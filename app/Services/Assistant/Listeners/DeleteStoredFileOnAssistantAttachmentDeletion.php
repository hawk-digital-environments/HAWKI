<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Services\Assistant\Events\AssistantAttachmentDeletingEvent;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use Psr\Log\LoggerInterface;

/**
 * Removes the physical file from storage whenever an AssistantAttachment
 * record is deleted.
 *
 * Failures are logged but do not interrupt the Eloquent deletion, so an
 * orphaned file on disk is possible if storage is temporarily unavailable.
 */
readonly class DeleteStoredFileOnAssistantAttachmentDeletion
{
    public function __construct(
        private FileStorageService $storageService,
        private LoggerInterface $logger
    ) {
    }

    public function handle(AssistantAttachmentDeletingEvent $event): void
    {
        try {
            $identifier = StoredFileIdentifier::fromAssistantAttachment($event->assistantAttachment);
            if (!$this->storageService->delete($identifier)) {
                $this->logger->error(
                    'Failed to delete stored file for assistant attachment',
                    ['uuid' => $event->assistantAttachment->uuid]
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Exception while deleting stored file for assistant attachment', ['exception' => $e]);
        }
    }
}
