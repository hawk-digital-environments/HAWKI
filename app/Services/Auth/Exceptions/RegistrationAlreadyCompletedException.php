<?php
declare(strict_types=1);

namespace App\Services\Auth\Exceptions;

/**
 * The account has a completed registration (keychain initialized), so another completion
 * request must not run. Mapped to the stable `registration_already_completed` error code.
 */
class RegistrationAlreadyCompletedException extends \RuntimeException implements AuthExceptionInterface
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forUsername(string $username): self
    {
        return new self(sprintf(
            'The account "%s" was already initialized by another registration request. '
            . 'The registration is finished; do not repeat the completion request.',
            $username
        ));
    }
}
