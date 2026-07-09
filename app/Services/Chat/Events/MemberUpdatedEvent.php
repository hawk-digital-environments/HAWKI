<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when an existing room member's record is modified (e.g. role change).
 *
 * Dispatched by {@see \App\Models\Room} when Eloquent detects a change on the member
 * pivot record. The {@see AbstractMemberEvent::$member} property holds the updated membership.
 *
 * @see MemberAddedToRoomEvent    for when a user first joins the room
 * @see MemberRemovedFromRoomEvent for when the member is removed
 */
readonly class MemberUpdatedEvent extends AbstractMemberEvent
{
}
