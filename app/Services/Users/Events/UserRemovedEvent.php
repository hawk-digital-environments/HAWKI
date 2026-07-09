<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

/**
 * Fired when a user account is deleted.
 *
 * Listeners should use this event to clean up any user-scoped data
 * (e.g. ExtApp connections, keychains, access tokens).
 * The {@see AbstractUserEvent::$user} property holds the user that was removed.
 *
 * @see UserCreatedEvent for when the user account is provisioned
 * @see UserUpdatedEvent for when the user's data changes while active
 */
readonly class UserRemovedEvent extends AbstractUserEvent
{
}
