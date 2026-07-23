<?php
declare(strict_types=1);

namespace App\Services\ExtApp\Events;

/**
 * Fired when an external app user account is deleted.
 *
 * Triggered by the ExtAppUser model's Eloquent `deleted` event via
 * {@see \App\Models\ExtAppUser::$dispatchesEvents}.
 * The {@see AbstractExtAppUserEvent::$user} property holds the deleted ExtApp user.
 *
 * @see ExtAppUserCreatedEvent for when the account is provisioned
 */
readonly class ExtAppUserRemovedEvent extends AbstractExtAppUserEvent
{
}
