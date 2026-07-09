<?php
declare(strict_types=1);

namespace App\Services\Chat\Attachment\Events;

/**
 * Fired when an attachment is unlinked (removed) from a message.
 *
 * Both the message and the attachment are still accessible when this event fires:
 * - {@see AbstractMessageAttachmentEvent::$message}    — the message the attachment was removed from
 * - {@see AbstractMessageAttachmentEvent::$attachment} — the attachment that was unlinked
 *
 * @see AttachmentAssignedToMessageEvent for when an attachment is linked to a message
 */
readonly class AttachmentRemovedFromMessageEvent extends AbstractMessageAttachmentEvent
{
}
