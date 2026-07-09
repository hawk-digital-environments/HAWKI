<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

use App\Models\UserKeychainValue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for all user keychain value lifecycle events.
 *
 * A keychain value stores a single piece of encrypted per-user data (e.g. an API key or
 * a personal secret). These events fire whenever such a value is created, changed, or removed.
 *
 * @see UserKeychainValueCreatedEvent  fired when a new keychain entry is added
 * @see UserKeychainValueUpdatedEvent  fired when a keychain entry's value changes
 * @see UserKeychainValueDeletingEvent fired before a keychain entry is deleted
 */
abstract readonly class AbstractUserKeychainValueEvent
{
    use Dispatchable;

    public function __construct(
        /** The keychain value that was created, updated, or is being deleted. */
        public UserKeychainValue $value
    ) {}
}
