<?php
declare(strict_types=1);

namespace App\Services\Users\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Base class for Sanctum personal access token lifecycle events.
 *
 * Provides access to both the {@see User} that owns the token and the token itself.
 * The token may be either an already-persisted {@see PersonalAccessToken} or a
 * freshly-created {@see NewAccessToken} (which wraps the plain-text token and is only
 * available immediately after generation — store it if needed).
 *
 * @see PersonalAccessTokenCreatedEvent for when a token is issued
 * @see PersonalAccessTokenRemovedEvent for when a token is revoked
 */
abstract readonly class AbstractPersonalAccessTokenEvent
{
    use Dispatchable;

    public function __construct(
        /** The user that owns this access token. */
        public User                               $user,
        /**
         * The access token that was created or removed.
         *
         * Will be a {@see NewAccessToken} (containing the plain-text token) on creation,
         * or a {@see PersonalAccessToken} (without the plain-text) on removal.
         */
        public PersonalAccessToken|NewAccessToken $token
    ) {}
}
