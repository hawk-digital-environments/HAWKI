<?php
declare(strict_types=1);

namespace App\Services\Chat\Attachment\Events;

use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for events that track when an attachment is linked to or unlinked from a message.
 *
 * Provides access to both the {@see Message} and the {@see Attachment} model involved.
 *
 * @see AttachmentAssignedToMessageEvent  fired when an attachment is linked to a message
 * @see AttachmentRemovedFromMessageEvent fired when an attachment is unlinked from a message
 */
abstract readonly class AbstractMessageAttachmentEvent
{
    use Dispatchable;

    public function __construct(
        /** The message the attachment was linked to or unlinked from. */
        public Message    $message,
        /** The attachment that was assigned or removed. */
        public Attachment $attachment
    ) {}
}
