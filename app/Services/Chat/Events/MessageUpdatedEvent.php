<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when an existing message is modified.
 *
 * Dispatched in multiple scenarios: when the message handler updates a message,
 * when the Message model's Eloquent `updated` hook fires, and when the message DB
 * helper updates a parent thread message after a reply is sent.
 * The {@see AbstractMessageEvent::$message} property holds the message in its updated state.
 *
 * @see MessageSentEvent for when a message is first created
 */
readonly class MessageUpdatedEvent extends AbstractMessageEvent
{
}
