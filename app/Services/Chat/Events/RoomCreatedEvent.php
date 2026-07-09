<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired after a new room has been persisted to the database.
 *
 * Use this event to react to room creation — e.g. to set up default members,
 * send creation notifications, or populate room-level configuration.
 *
 * The {@see AbstractRoomEvent::$room} property holds the newly created room.
 *
 * @see RoomUpdatedEvent  for subsequent changes to the room
 * @see RoomDeletingEvent for when the room is removed
 */
readonly class RoomCreatedEvent extends AbstractRoomEvent
{
}
