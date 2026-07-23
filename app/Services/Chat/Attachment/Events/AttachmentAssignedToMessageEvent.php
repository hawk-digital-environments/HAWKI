<?php
declare(strict_types=1);

namespace App\Services\Chat\Attachment\Events;

/**
 * Fired when an attachment is linked (assigned) to a message.
 *
 * Both the message and the attachment are available after this event fires:
 * - {@see AbstractMessageAttachmentEvent::$message}    — the message that now has the attachment
 * - {@see AbstractMessageAttachmentEvent::$attachment} — the attachment that was linked
 *
 * @see AttachmentRemovedFromMessageEvent for when the attachment is later unlinked
 */
readonly class AttachmentAssignedToMessageEvent extends AbstractMessageAttachmentEvent
{
}
