<?php

declare(strict_types=1);

namespace App\Services\Users\Exceptions;

use App\Services\Users\Settings\AbstractUserSettings;

/**
 * Thrown when a class that is not a user-settings object is passed to
 * {@see \App\Services\Users\Settings\UserSettingsService}.
 */
class InvalidUserSettingsClassException extends \InvalidArgumentException implements UsersExceptionInterface
{
    /**
     * Creates the exception for a class that does not extend the user-settings base class.
     */
    public static function forClass(string $settingsClass): self
    {
        return new self(\sprintf(
            'The class "%s" must extend "%s" to be loadable via the user-settings service.',
            $settingsClass,
            AbstractUserSettings::class,
        ));
    }
}
