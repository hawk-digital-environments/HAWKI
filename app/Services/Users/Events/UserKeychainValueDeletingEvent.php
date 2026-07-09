<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

/**
 * Fired before a user keychain value is deleted from the database.
 *
 * Triggered by the UserKeychainValue model's Eloquent `deleting` event via
 * {@see \App\Models\UserKeychainValue::$dispatchesEvents}. The entry is still
 * readable via {@see AbstractUserKeychainValueEvent::$value} when this event fires.
 *
 * @see UserKeychainValueCreatedEvent for when the entry is first created
 * @see UserKeychainValueUpdatedEvent for when the entry's value changes
 */
readonly class UserKeychainValueDeletingEvent extends AbstractUserKeychainValueEvent
{
}
