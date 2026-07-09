<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when a user is added to a room as a member.
 *
 * Dispatched by {@see \App\Models\Room} when Eloquent detects a new member pivot record.
 * The {@see AbstractMemberEvent::$member} property holds the newly created membership.
 *
 * @see MemberUpdatedEvent        for when the member's data changes after joining
 * @see MemberRemovedFromRoomEvent for when the member leaves or is removed
 */
readonly class MemberAddedToRoomEvent extends AbstractMemberEvent
{
}
