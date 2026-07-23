<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all message lifecycle events.
 *
 * Provides access to the {@see Message} model that was affected.
 *
 * @see MessageSentEvent    fired when a new message is sent
 * @see MessageUpdatedEvent fired when a message's content or metadata changes
 */
abstract readonly class AbstractMessageEvent
{
    use Dispatchable;

    public function __construct(
        /** The message that was sent or updated. */
        public Message $message
    ) {}
}
