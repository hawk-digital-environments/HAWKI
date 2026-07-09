<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

use App\Models\Room;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all room lifecycle events.
 *
 * Provides access to the {@see Room} model that was affected. Concrete subclasses
 * are dispatched by the Room model and the Chat service at key lifecycle moments.
 *
 * @see RoomCreatedEvent  fired when a new room is persisted
 * @see RoomUpdatedEvent  fired when a room's fields are changed
 * @see RoomDeletingEvent fired before a room is deleted
 */
abstract readonly class AbstractRoomEvent
{
    use Dispatchable;

    public function __construct(
        /** The room that was created, updated, or is being deleted. */
        public Room $room
    ) {}
}
