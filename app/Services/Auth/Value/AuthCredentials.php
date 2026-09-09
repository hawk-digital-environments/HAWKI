<?php
declare(strict_types=1);


namespace App\Services\Auth\Value;


use SensitiveParameter;

/**
 * The username/password pair for a single login attempt against a credential-based auth service.
 *
 * Exists so callers can hand credentials to {@see \App\Services\Auth\LoginHandler} without the
 * handler having to know where they came from — a legacy form post, a JSON:API action body, or
 * a test.
 */
readonly class AuthCredentials
{
    public function __construct(
        /**
         * The account identifier as typed by the user. Passed through `filter_var()` with
         * `FILTER_UNSAFE_RAW` on the way in, matching what the legacy login form did.
         */
        public string $account,
        #[SensitiveParameter]
        public string $password
    )
    {
    }
}
