<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

use App\Models\Member;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all room-membership lifecycle events.
 *
 * Provides access to the {@see Member} model that was affected. The member model
 * links a user to a room and carries their role and permissions within that room.
 *
 * @see MemberAddedToRoomEvent    fired when a user becomes a member of a room
 * @see MemberUpdatedEvent        fired when a member's role or data changes
 * @see MemberRemovedFromRoomEvent fired when a user is removed from a room
 */
abstract readonly class AbstractMemberEvent
{
    use Dispatchable;

    public function __construct(
        /** The membership record that was created, changed, or removed. */
        public Member $member
    ) {}
}
