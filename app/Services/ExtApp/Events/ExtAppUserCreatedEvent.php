<?php
declare(strict_types=1);

namespace App\Services\ExtApp\Events;

/**
 * Fired when an external app user account is provisioned.
 *
 * Triggered by the ExtAppUser model's Eloquent `created` event via
 * {@see \App\Models\ExtAppUser::$dispatchesEvents}.
 * The {@see AbstractExtAppUserEvent::$user} property holds the newly created ExtApp user.
 *
 * @see ExtAppUserRemovedEvent for when the account is deleted
 */
readonly class ExtAppUserCreatedEvent extends AbstractExtAppUserEvent
{
}
