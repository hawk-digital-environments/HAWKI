<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

/**
 * Fired when a personal access token is revoked (deleted).
 *
 * Listeners should use this event to clean up any state associated with the token
 * (e.g. active ExtApp sessions that relied on it).
 * The {@see AbstractPersonalAccessTokenEvent::$token} property holds the revoked token record.
 *
 * @see PersonalAccessTokenCreatedEvent for when a token is issued
 */
readonly class PersonalAccessTokenRemovedEvent extends AbstractPersonalAccessTokenEvent
{
}
