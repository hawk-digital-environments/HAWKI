<?php
declare(strict_types=1);

namespace App\Services\Chat\Events;

/**
 * Fired when a room invitation is updated (e.g. accepted, declined, or revoked).
 *
 * Triggered by the Invitation model's Eloquent `updated` event via
 * {@see \App\Models\Invitation::$dispatchesEvents}.
 * The {@see AbstractInvitationEvent::$invitation} property holds the invitation in its updated state.
 *
 * @see InvitationCreatedEvent for when the invitation is first issued
 */
readonly class InvitationUpdatedEvent extends AbstractInvitationEvent
{
}
