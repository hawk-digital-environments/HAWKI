<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

/**
 * Fired when a new user keychain value is persisted to the database.
 *
 * Triggered by the UserKeychainValue model's Eloquent `created` event via
 * {@see \App\Models\UserKeychainValue::$dispatchesEvents}.
 * The {@see AbstractUserKeychainValueEvent::$value} property holds the new keychain entry.
 *
 * @see UserKeychainValueUpdatedEvent  for when the entry's value changes
 * @see UserKeychainValueDeletingEvent for when the entry is removed
 */
readonly class UserKeychainValueCreatedEvent extends AbstractUserKeychainValueEvent
{
}
