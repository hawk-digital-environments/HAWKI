<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

use App\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all room-invitation lifecycle events.
 *
 * Provides access to the {@see Invitation} model that was affected.
 *
 * @see InvitationCreatedEvent for when a new invitation is issued
 * @see InvitationUpdatedEvent for when an invitation's state changes (e.g. accepted, revoked)
 */
abstract readonly class AbstractInvitationEvent
{
    use Dispatchable;

    public function __construct(
        /** The invitation that was created or updated. */
        public Invitation $invitation
    ) {}
}
