<?php
declare(strict_types=1);


namespace App\Services\Auth\Value;


/**
 * What the configured auth service lets the frontend do, beyond picking a {@see AuthMode}.
 *
 * Kept as its own object so future abilities (passkey-only login, a second factor, an account
 * chooser) can be added without turning `mode` into a combinatorial enum.
 */
readonly class AuthCapabilities
{
    public function __construct(
        /**
         * The service accepts a username and password posted to the login action.
         * True in credentials and mixed mode.
         */
        public bool $credentials,
        /** An external identity-provider redirect is available. */
        public bool $redirect,
    )
    {
    }
}
