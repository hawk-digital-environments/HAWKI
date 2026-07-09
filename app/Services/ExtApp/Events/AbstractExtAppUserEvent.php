<?php
declare(strict_types=1);

namespace App\Services\ExtApp\Events;

use App\Models\ExtAppUser;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for events related to external-app user accounts.
 *
 * An {@see ExtAppUser} represents a HAWKI user identity that was provisioned through
 * an external app integration (as opposed to direct login). These events fire when
 * such an account is created or removed.
 *
 * @see ExtAppUserCreatedEvent for when an ExtApp user account is provisioned
 * @see ExtAppUserRemovedEvent for when an ExtApp user account is deleted
 */
abstract readonly class AbstractExtAppUserEvent
{
    use Dispatchable;

    public function __construct(
        /** The external app user that was created or removed. */
        public ExtAppUser $user
    ) {}
}
