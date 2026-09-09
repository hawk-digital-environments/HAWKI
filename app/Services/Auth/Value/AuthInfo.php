<?php
declare(strict_types=1);


namespace App\Services\Auth\Value;


/**
 * What the frontend needs to render a login screen, sent via the JSON:API `auth` resource.
 *
 * The interesting part is the {@see mode}/{@see startUrl} pair, which is a discriminated union:
 *
 * - {@see AuthMode::CREDENTIALS} → {@see startUrl} is `null`; there is a form to fill in.
 * - {@see AuthMode::REDIRECT} → {@see startUrl} points at the route that kicks off the identity
 *   provider round trip; there is nothing to fill in.
 *
 * @see \App\Services\Auth\AuthInfoFactory  Builds this from the configured auth service.
 */
readonly class AuthInfo
{
    public function __construct(
        /** Always `"hawki"` — the native instance. Mirrors the connection resource's id. */
        public string           $id,
        public AuthMode         $mode,
        /** Where to send the browser to start a redirect login; `null` in credentials mode. */
        public string|null      $startUrl,
        public AuthCapabilities $capabilities,
        /**
         * A stable error code from the *previous*, failed login attempt, or `null`.
         * One of `invalid_credentials` or `provider_failed`; the frontend translates it.
         *
         * One-shot: it is pulled out of the session when this object is built, so the code
         * is shown once and does not reappear on the next poll of the resource.
         */
        public string|null      $lastError = null
    )
    {
    }
}
