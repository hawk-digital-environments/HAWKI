<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

/**
 * Fired when an existing user's data is changed.
 *
 * The {@see AbstractUserEvent::$user} property holds the user in its post-update state.
 *
 * @see UserCreatedEvent for when the user account is first provisioned
 * @see UserRemovedEvent for when the user account is deleted
 */
readonly class UserUpdatedEvent extends AbstractUserEvent
{
}
