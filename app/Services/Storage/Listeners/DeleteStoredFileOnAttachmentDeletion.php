<?php
declare(strict_types=1);

namespace App\Services\Storage\Listeners;

use App\Services\Chat\Attachment\Events\AttachmentDeleting;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use Psr\Log\LoggerInterface;

/**
 * Removes the physical file from storage whenever an Attachment record is deleted.
 *
 * Failures are logged but do not interrupt the Eloquent deletion, so an orphaned file
 * on disk is possible if storage is temporarily unavailable. Scheduled cleanup should
 * handle that case.
 */
readonly class DeleteStoredFileOnAttachmentDeletion
{
    public function __construct(
        private FileStorageService $storageService,
        private LoggerInterface    $logger
    )
    {
    }

    public function handle(AttachmentDeleting $event): void
    {
        try {
            $identifier = StoredFileIdentifier::fromAttachment($event->attachment);
            if (!$this->storageService->delete($identifier)) {
                $this->logger->error(
                    'Failed to delete stored file for attachment',
                    ['uuid' => $event->attachment->uuid, 'category' => $event->attachment->category]
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Exception while deleting stored file for attachment', ['exception' => $e]);
        }
    }
}
