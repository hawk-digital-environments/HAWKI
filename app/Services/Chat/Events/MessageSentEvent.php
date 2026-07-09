<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when a new message is sent in a room.
 *
 * Dispatched by the message handler after the message has been persisted.
 * The {@see AbstractMessageEvent::$message} property holds the fully saved message,
 * including its ID, room association, and any initial content.
 *
 * @see MessageUpdatedEvent for when a message's content or metadata is changed after sending
 */
readonly class MessageSentEvent extends AbstractMessageEvent
{
}
