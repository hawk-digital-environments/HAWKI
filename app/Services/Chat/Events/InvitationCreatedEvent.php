<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when a new room invitation is created.
 *
 * Triggered by the Invitation model's Eloquent `created` event via
 * {@see \App\Models\Invitation::$dispatchesEvents}.
 * The {@see AbstractInvitationEvent::$invitation} property holds the new invitation.
 *
 * @see InvitationUpdatedEvent for when the invitation's state changes
 */
readonly class InvitationCreatedEvent extends AbstractInvitationEvent
{
}
