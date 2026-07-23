<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

/**
 * Fired when a new personal access token is issued for a user.
 *
 * Dispatched by the ExtApp user repository after Sanctum generates the token.
 * At the time this event fires the {@see AbstractPersonalAccessTokenEvent::$token}
 * property holds a {@see \Laravel\Sanctum\NewAccessToken}, which includes the
 * plain-text token string — this is the only moment it is available in plain text.
 *
 * @see PersonalAccessTokenRemovedEvent for when the token is revoked
 */
readonly class PersonalAccessTokenCreatedEvent extends AbstractPersonalAccessTokenEvent
{
}
