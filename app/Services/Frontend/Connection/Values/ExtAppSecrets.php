<?php
declare(strict_types=1);


namespace App\Services\Frontend\Connection\Values;


/**
 * Sensitive per-user credentials delivered to the frontend for an authenticated external-app session.
 *
 * These values are only included in the connection response for `EXTERNAL_APP_AUTHENTICATED`
 * connections. The frontend uses them to decrypt the user's private data and authenticate
 * subsequent API requests on behalf of the external-app user.
 */
readonly class ExtAppSecrets
{
    public function __construct(
        /** The user's passkey, required by the frontend to decrypt locally-encrypted data. */
        public string $passkey,
        /** Bearer token for authenticating API requests on behalf of this user. */
        public string $apiToken,
        /** The user's encrypted private key, used for client-side cryptographic operations. */
        public string $privateKey,
    )
    {
    }
}
