<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when a user is removed from a room.
 *
 * Dispatched by {@see \App\Models\Room} when Eloquent detects a deleted member pivot record.
 * The {@see AbstractMemberEvent::$member} property holds the membership that was removed.
 * At the time this event fires the record has already been deleted from the database.
 *
 * @see MemberAddedToRoomEvent for when a user joins the room
 * @see MemberUpdatedEvent     for when a member's data changes while still in the room
 */
readonly class MemberRemovedFromRoomEvent extends AbstractMemberEvent
{
}
