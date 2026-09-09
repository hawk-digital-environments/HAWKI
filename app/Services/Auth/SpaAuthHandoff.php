<?php
declare(strict_types=1);


namespace App\Services\Auth;


use App\Services\Auth\Value\LoginNextStep;
use App\Services\Auth\Value\SpaAuthPage;
use App\Services\Auth\Value\SpaNextTarget;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Http\Request;

/**
 * @api
 *
 * Carries a redirect-based login (OIDC, Shibboleth) across the identity provider round trip, so
 * the visitor lands back in the Svelte SPA instead of the legacy Blade gateway.
 *
 * The callback URL is registered with the identity provider and shared by both UIs, so which UI
 * started the login cannot be read off the callback request. Instead `/auth/redirect` leaves a
 * marker in the session on the way out, and the callback consumes it on the way back:
 *
 * ```
 * GET /auth/redirect?next=/new/chat/abc  → start()            → marker + next in session → IdP
 * GET /req/login   (the IdP callback)    → isPending() = true
 *                                        → completeSuccess()  → /new/auth/handshake?next=…
 * ```
 *
 * Consuming the marker is what keeps the legacy flow intact: a callback without a marker — a
 * legacy login, a stale tab, a replayed URL — takes the old code path untouched.
 */
#[Singleton]
readonly class SpaAuthHandoff
{
    /**
     * Session key holding the last stable login error code. Read (and cleared) through the
     * `auth` JSON:API resource's `last_error` attribute.
     * @see \App\Services\Auth\AuthInfoFactory
     */
    public const string SESSION_ERROR_KEY = 'auth.last_error';
    public const string SESSION_REGISTRATION_UI_KEY = 'auth.registration_ui';

    private const string SESSION_UI_KEY = 'auth.ui';
    private const string SESSION_NEXT_KEY = 'auth.next';
    private const string UI_MARKER = 'new';

    public function isEnabled(): bool
    {
        return config('hawki.spa_auth', false);
    }

    public function urlFor(SpaAuthPage|LoginNextStep $step): string
    {
        return '/new/auth/' . $step->value;
    }

    public function entryUrlFor(SpaAuthPage|LoginNextStep $step): string
    {
        return $this->isEnabled() ? $this->urlFor($step) : '/' . $step->value;
    }

    public function requiresSpaRegistration(Request $request): bool
    {
        return $this->isEnabled() || $this->isSpaRegistration($request);
    }

    /**
     * Marks the login that is about to start as belonging to the SPA and remembers where the
     * visitor wanted to go, if they told us and the target survived validation.
     *
     * @param string|null $rawNext The unvalidated `next` query value; see {@see SpaNextTarget}.
     */
    public function start(Request $request, ?string $rawNext): void
    {
        $session = $request->session();
        $session->put(self::SESSION_UI_KEY, self::UI_MARKER);
        $session->put(self::SESSION_NEXT_KEY, SpaNextTarget::tryFrom($rawNext)?->path);

        // The OIDC client redirects with a bare header() + exit, which never reaches Laravel's
        // terminate stage — so the marker has to be written to the session store right now or
        // it is lost before the identity provider is even contacted.
        $session->save();
    }

    /**
     * True when the login in progress was started from the SPA. Only peeks; call
     * {@see completeSuccess()} or {@see completeFailure()} to actually consume the marker.
     */
    public function isPending(Request $request): bool
    {
        return $request->hasSession()
            && $request->session()->get(self::SESSION_UI_KEY) === self::UI_MARKER;
    }

    /** A new credentials login or a return to the legacy login page abandons the provider round trip. */
    public function discard(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget([self::SESSION_UI_KEY, self::SESSION_NEXT_KEY, self::SESSION_ERROR_KEY]);
        }
    }

    public function markSpaRegistration(Request $request): void
    {
        $request->session()->put(self::SESSION_REGISTRATION_UI_KEY, self::UI_MARKER);
    }

    public function isSpaRegistration(Request $request): bool
    {
        return $request->hasSession()
            && $request->session()->get(self::SESSION_REGISTRATION_UI_KEY) === self::UI_MARKER;
    }

    /**
     * Consumes the handoff and returns the SPA URL for the step the visitor reached, carrying
     * the remembered `next` along as a query parameter.
     */
    public function completeSuccess(Request $request, LoginNextStep $step): string
    {
        if ($step === LoginNextStep::REGISTER) {
            $this->markSpaRegistration($request);
        }

        $next = $this->consume($request);

        $url = $this->urlFor($step);

        return $next === null ? $url : $url . '?' . http_build_query(['next' => $next->path]);
    }

    /**
     * Consumes the handoff, stores a stable error code for the SPA's login screen to pick up through the
     * `auth` resource, and returns the SPA login URL.
     *
     * @param 'invalid_credentials'|'provider_failed' $code
     */
    public function completeFailure(Request $request, string $code): string
    {
        $next = $this->consume($request);

        // Stored, not flashed. Laravel's flash data is aged out at the end of the *next*
        // request, and the next request here is the SPA's HTML page load — the message would be
        // gone by the time the booting frontend asks the `auth` resource for it. The one-shot
        // behaviour comes from {@see pullLastError()} instead.
        $request->session()->put(self::SESSION_ERROR_KEY, $code);

        return $next === null ? $this->urlFor(SpaAuthPage::LOGIN) : $this->urlFor(SpaAuthPage::LOGIN) . '?' . http_build_query(['next' => $next->path]);
    }

    /**
     * Reads and clears the last login error. One-shot on purpose: the SPA boots by polling the
     * `auth` resource, and a sticky error would resurface on every poll after the user has
     * already been shown it.
     */
    public function pullLastError(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $error = $request->session()->pull(self::SESSION_ERROR_KEY);

        return is_string($error) && $error !== '' ? $error : null;
    }

    private function consume(Request $request): ?SpaNextTarget
    {
        $session = $request->session();
        $session->forget(self::SESSION_UI_KEY);

        // Re-validate on the way out: the value went through the session and the prefix rules
        // may have tightened since it was stored.
        $next = $session->pull(self::SESSION_NEXT_KEY);

        return SpaNextTarget::tryFrom(is_string($next) ? $next : null);
    }
}
