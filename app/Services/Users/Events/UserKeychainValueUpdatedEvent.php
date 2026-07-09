<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

/**
 * Fired when an existing user keychain value is changed.
 *
 * Triggered by the UserKeychainValue model's Eloquent `updated` event via
 * {@see \App\Models\UserKeychainValue::$dispatchesEvents}.
 * The {@see AbstractUserKeychainValueEvent::$value} property holds the entry in its updated state.
 *
 * @see UserKeychainValueCreatedEvent  for when the entry is first created
 * @see UserKeychainValueDeletingEvent for when the entry is removed
 */
readonly class UserKeychainValueUpdatedEvent extends AbstractUserKeychainValueEvent
{
}
