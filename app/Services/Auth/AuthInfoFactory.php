<?php
declare(strict_types=1);


namespace App\Services\Auth;


use App\Services\Auth\Value\AuthCapabilities;
use App\Services\Auth\Value\AuthInfo;
use App\Services\Auth\Value\AuthMode;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Request;

/**
 * @api
 *
 * Builds the {@see AuthInfo} value behind the JSON:API `auth` resource.
 *
 * All of it is derived, nothing is configured twice: the mode comes from which interfaces the
 * service bound to `AuthServiceInterface` implements, so switching `AUTHENTICATION_METHOD` from
 * LDAP to OIDC changes the login screen without anyone editing a second setting.
 *
 * Usage (in `AuthRepository`):
 * ```php
 * $authInfo = $factory->createHawkiAuthInfo();
 * ```
 */
#[Singleton]
readonly class AuthInfoFactory
{
    /**
     * The web route that starts a redirect login. Kept here rather than resolved through the
     * router because it is part of the published API contract — the frontend navigates to it
     * verbatim.
     * @see \App\Http\Controllers\AuthenticationController::startRedirectLogin()
     */
    public const string REDIRECT_START_URL = '/auth/redirect';

    public function __construct(
        private LoginHandler   $loginHandler,
        private Request        $request,
        private SpaAuthHandoff $handoff
    )
    {
    }

    /**
     * Describes how to log in to this HAWKI instance.
     *
     * Building the value consumes the pending login error, if there is one — see
     * {@see AuthInfo::$lastError}.
     */
    public function createHawkiAuthInfo(): AuthInfo
    {
        $acceptsCredentials = $this->loginHandler->requiresCredentials();

        return new AuthInfo(
            id: 'hawki',
            mode: $acceptsCredentials ? AuthMode::CREDENTIALS : AuthMode::REDIRECT,
            startUrl: $acceptsCredentials ? null : self::REDIRECT_START_URL,
            capabilities: new AuthCapabilities(credentials: $acceptsCredentials),
            lastError: $this->handoff->pullLastError($this->request)
        );
    }
}
