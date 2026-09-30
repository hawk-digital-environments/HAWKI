<?php
declare(strict_types=1);


namespace App\Services\Auth;


use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Contract\AuthServiceWithLogoutRedirectInterface;
use Illuminate\Auth\AuthManager;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Http\Request;

/**
 * @api
 *
 * Tears down the current session and reports where — if anywhere — the identity provider wants
 * the browser to go next.
 *
 * The ordering here is the whole point of the class: the provider's logout URL is built
 * **before** anything is cleared, because building it reads session state (the OIDC
 * `id_token_hint`) that is gone a moment later. Getting this backwards produces a logout URL
 * without a token hint, which some identity providers answer with a consent prompt and others
 * reject outright — and it only shows up in a real SSO setup, never in local development.
 *
 * ```php
 * $providerUrl = $logoutHandler->handle($request);
 * return redirect($providerUrl ?? '/login');
 * ```
 */
#[Singleton]
readonly class LogoutHandler
{
    public function __construct(
        private AuthServiceInterface $authService,
        private AuthManager          $auth,
        private CookieJar            $cookies
    )
    {
    }

    /**
     * Logs the visitor out, invalidating the session and rotating the CSRF token.
     *
     * @return string|null The identity provider's logout URL, when the configured auth service
     *                     has one. Callers redirect there if set, and to their own login page
     *                     otherwise.
     */
    public function handle(Request $request): ?string
    {
        $providerLogoutUrl = $this->resolveProviderLogoutUrl($request);

        $this->auth->logout();

        // invalidate() flushes the session data and regenerates its id; regenerateToken()
        // additionally rotates the CSRF token, so a page left open cannot post as the old user.
        $session = $request->session();
        $session->invalidate();
        $session->regenerateToken();

        // Laravel does not use PHPSESSID, but an upstream SSO module (mod_shib, mod_auth_openidc)
        // on the same host may have started a native PHP session under it.
        $this->cookies->queue($this->cookies->forget('PHPSESSID'));

        return $providerLogoutUrl;
    }

    private function resolveProviderLogoutUrl(Request $request): ?string
    {
        if (!$this->authService instanceof AuthServiceWithLogoutRedirectInterface) {
            return null;
        }

        return $this->authService->getLogoutResponse($request)?->getTargetUrl();
    }
}
