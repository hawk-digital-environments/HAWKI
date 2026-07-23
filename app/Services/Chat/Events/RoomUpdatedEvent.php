<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired after a room's data has been changed and saved to the database.
 *
 * Triggered by the Room model's Eloquent `updated` event via {@see \App\Models\Room::$dispatchesEvents}.
 * The {@see AbstractRoomEvent::$room} property holds the room in its post-update state.
 *
 * @see RoomCreatedEvent  fired when the room is first created
 * @see RoomDeletingEvent fired before the room is deleted
 */
readonly class RoomUpdatedEvent extends AbstractRoomEvent
{
}
