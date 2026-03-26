<?php
declare(strict_types=1);

namespace App\Services\Chat\Attachment\Events;

use App\Models\Attachment;

/**
 * Event fired before an attachment is deleted.
 * Listeners can use this event to perform cleanup tasks, such as deleting the associated stored file.
 */
readonly class AttachmentDeleting
{
    public function __construct(
        public Attachment $attachment
    )
    {
    }
}
