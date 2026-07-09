<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired before a room is deleted from the database.
 *
 * This event fires *before* the DELETE query executes, giving listeners a chance
 * to clean up related data (messages, memberships, keys, etc.) while the room
 * record is still reachable in the database.
 *
 * The {@see AbstractRoomEvent::$room} property holds the room about to be deleted.
 *
 * @see RoomCreatedEvent for when the room is first created
 * @see RoomUpdatedEvent for when the room's data changes
 */
readonly class RoomDeletingEvent extends AbstractRoomEvent
{
}
