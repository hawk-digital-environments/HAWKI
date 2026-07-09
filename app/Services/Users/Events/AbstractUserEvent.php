<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all user lifecycle events.
 *
 * Provides access to the {@see User} model that was affected.
 *
 * @see UserCreatedEvent fired when a new user account is provisioned
 * @see UserUpdatedEvent fired when a user's data changes
 * @see UserRemovedEvent fired when a user is deleted
 */
abstract readonly class AbstractUserEvent
{
    use Dispatchable;

    public function __construct(
        /** The user account that was created, updated, or removed. */
        public User $user
    ) {}
}
