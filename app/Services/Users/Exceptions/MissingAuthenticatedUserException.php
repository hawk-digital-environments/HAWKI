<?php

declare(strict_types=1);

namespace App\Services\Users\Exceptions;

use App\Services\Users\Settings\UserSettingsService;

/**
 * Thrown when the database-backed user-settings storage is accessed without an
 * authenticated user.
 *
 * This is a programming error: {@see UserSettingsService}
 * routes to the database storage only when the {@see \App\Services\System\UserTypes\UserContext}
 * resolves a fully authenticated user, so reaching this exception means the storage was
 * resolved outside the service's selection logic.
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
}
