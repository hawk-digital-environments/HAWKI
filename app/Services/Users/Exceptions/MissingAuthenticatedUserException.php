<?php

declare(strict_types=1);

namespace App\Services\Users\Exceptions;

use App\Services\Users\Favorites\UserFavoritesService;
use App\Services\Users\Settings\UserSettingsService;

/**
 * Thrown when a per-user, database-backed operation is attempted without an
 * authenticated user.
 *
 * Two sources:
 *
 * - {@see UserSettingsService}: a programming error — the service routes to the
 *   database storage only when the {@see \App\Services\System\UserTypes\UserContext}
 *   resolves a fully authenticated user, so reaching this exception means the
 *   storage was resolved outside the service's selection logic.
 * - {@see UserFavoritesService}: favorites have **no** guest storage backends, so
 *   this exception is also the *public* signal to callers (including the JSON:API
 *   layer and the frontend) that favorites require a logged-in user.
 */
class MissingAuthenticatedUserException extends \LogicException implements UsersExceptionInterface
{
    /**
     * Creates the exception for a storage operation attempted without an authenticated user.
     */
    public static function forSettingsStorageOperation(): self
    {
        return new self(\sprintf(
            'No authenticated user found for the database user-settings storage.'
            . ' The storage must only be used through %s, which routes to it only'
            . ' for fully authenticated users.',
            UserSettingsService::class,
        ));
    }

    /**
     * Creates the exception for a favorites operation attempted without an
     * authenticated user. Guests and registering users have no favorites —
     * log in (or finish registering) to favorite items.
     */
    public static function forFavoritesService(string $operation): self
    {
        return new self(\sprintf(
            'Cannot %s without an authenticated user. Favorites are stored per'
            . ' user account; guests and registering users have none. Log in to'
            . ' manage favorites.',
            $operation,
        ));
    }
}
