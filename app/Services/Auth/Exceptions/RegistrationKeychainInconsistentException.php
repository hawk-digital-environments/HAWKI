<?php
declare(strict_types=1);

namespace App\Services\Auth\Exceptions;

/**
 * The account already carries keychain data that this registration did not create, so
 * completing on top of it could make existing data undecryptable. Mapped to the stable
 * `registration_keychain_inconsistent` error code.
 */
class RegistrationKeychainInconsistentException extends \RuntimeException implements AuthExceptionInterface
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forUsername(string $username): self
    {
        return new self(sprintf(
            'The account "%s" already has keychain data that was not created by this registration. '
            . 'Reset the profile explicitly before completing another registration.',
            $username
        ));
    }
}
