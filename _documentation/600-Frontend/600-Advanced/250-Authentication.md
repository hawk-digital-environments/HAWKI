# SPA Authentication

The auth pages live under `/new/auth`: `login`, `register`, `handshake`, and `inconsistent`. Credentials and redirect providers share the same registration and keychain flows. Passkeys and backup codes are used only in the browser; the API receives encrypted keychain values and an encrypted passkey backup.

The built-in `auth` plugin (`resources/js/plugins/auth/auth.plugin.ts`) registers these routes and the auth resource schema. Its pages and UI helpers live in `plugins/auth/pages/`. Shared session handling and navigation guards remain in the kernel.

## Deployment

1. Run the database migrations before deploying the new frontend. The September 2026 migrations archive duplicate backups, add uniqueness constraints and consent fields, and persist registration fingerprints. The highest backup ID survives; archived copies expire after 90 days.
2. Publish an active global `policy` announcement with readable content in the requested or default locale. Registration returns `registration_policy_unavailable` when no usable policy exists. Deep health checks report this as `degraded` with HTTP 200. Quick probes only check connectivity and do not resolve policy content.
3. Keep Laravel's scheduler running. `passkey-backups:cleanup-archive` runs daily and safely skips installations without the archive table.
4. For OIDC, retain the registered `/req/login` callback and use `SESSION_SAME_SITE=lax`. Login starts through a document navigation to `/auth/redirect`.
5. Set `HAWKI_SPA_AUTH=true` to send the legacy `/login`, `/handshake`, and `/register` entry routes to the SPA. Refresh Laravel's configuration cache when changing this setting. The default is `false`, so switching those entry routes remains an explicit rollout step.

## Route access

Routes declare `meta.access` as `public`, `server-session`, or `crypto-ready`. The default, including the fallback route, is `crypto-ready`. `meta.chrome: 'none'` hides the application sidebar on auth pages.

Authentication and decrypted key availability are separate: `app.isAuthenticated` reports the server identity; `app.cryptoReady` becomes true only after passkey validation, `after_passkey` migrations, and keychain loading. `after_login` migrations run during the bootstrap migration stage. Guest boots skip them.

The `next` query parameter accepts only validated paths under `/new/`. It travels through login and setup and is followed after the keychain is ready. A manual reset discards it.

## Registration and recovery

Set `APP_SECURITY_PASSKEY_AUTO_GENERATE=true` to generate a random 256-bit passkey in the browser during registration. Both the SPA and legacy registration skip manual passkey entry and show the backup code for download after any required policy consent. The default is `false`. Refresh Laravel's configuration cache after changing the setting. Existing passkeys remain valid. Users need the backup code to restore access when the locally stored passkey is unavailable, including on a new device. With this option enabled, both unlock screens open directly on backup-code entry. Users can still switch to passkey entry for existing manually configured accounts.

Registration submits policy consent, encrypted keys, and the encrypted backup in one transaction. A repeated identical payload returns success without rewriting keys; a different payload after completion returns `registration_already_completed`. If the policy changes, the browser requests fresh consent while retaining the generated key material. It stores the encrypted local passkey before reloading into the handshake. Laravel's session guard rotates the session ID when completion signs the user in.

The server remembers when a registering session enters the SPA, including direct `/new` access while the rollout flag is disabled. Those sessions cannot use the legacy completion or backup-write endpoints, and `/register` redirects them to SPA setup. Legacy completion allows retries until the account has a public key; an initialized account returns JSON with HTTP 409. Opening legacy login discards an abandoned SPA provider handoff.

Both registration pages use the kernel's passkey generator. The legacy bridge exposes it to the existing page and retains `initializeNewKeychain()` for that page's separate completion step. These adapters have removal TODOs for the end of the legacy rollout; SPA registration uses the atomic completion action.

An inconsistent keychain leads to an explanation and an explicit reset action. It never triggers an automatic clean write. Backup recovery reads the JSON:API resource `/api/hawki/v1/passkey-backups/me` through `app.restApi.getResource('passkey-backups', 'me')`, decrypts locally, and follows the same validation and migration sequence as manual passkey entry. Other resource IDs never expose another user's backup.

Logout locks the keychain and clears decrypted keys from memory before contacting the API. The encrypted passkey remains in browser storage, scoped to the account username. After the same account signs in again, the browser restores that passkey and validates it against the server keychain before unlocking. A different account never restores the previous account's passkey. Profile resets and explicit local passkey removal still delete the stored copy. A failed request leaves a retry screen, and background session checks cannot interrupt that flow. For other API requests, HTTP 401 or 419 during an established login or registration session locks the in-memory keychain while retaining the encrypted browser passkey and returns to login, including when the current page is a handshake or setup page. The redirect preserves the original validated destination rather than making the auth page itself the next destination. Invalid credentials submitted by a guest remain a login-form error.

## Verification

Run the affected checks through the repository runner. The two standalone `--` arguments forward PHPUnit filters through the wrappers:

```bash
bin/env npm run check
bin/env npm run test:auth
bin/env test php stan
bin/env test php unit -- -- --filter 'Services\\(Auth|Users\\Keychain|System\\Health)|SecurityConfig'
bin/env test php feature -- -- --filter 'Registration|SpaAuthFlowTest'
```

PHPUnit forces `APP_ENV=testing`; the SPA auth tests obtain an XSRF cookie and send its token through the CSRF middleware. The feature tests use an isolated in-memory SQLite database. Coverage includes completion retries, rollback, archive cleanup, policy consent, stale handoffs, session rotation, required migrations, and retaining the encrypted passkey across logout for the same account. A deployment should additionally exercise the configured identity provider's login callback and logout return URL.

## Upgrade notes

The identity provider must have `/req/login` registered as the OIDC callback. The callback is pinned because login now starts at `/auth/redirect`. Configure `APP_URL` and trusted proxies correctly so `url()` and `route()` generate the public scheme and host.
