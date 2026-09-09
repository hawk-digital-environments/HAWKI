# SPA Authentication

The auth pages live under `/new/auth`: `login`, `register`, `handshake`, and `inconsistent`. Credentials and redirect providers share the same registration and keychain flows. Passkeys and backup codes are used only in the browser; the API receives encrypted keychain values and an encrypted passkey backup.

## Deployment

1. Run the database migrations before deploying the new frontend. The September 2026 migrations archive duplicate backups, add uniqueness constraints and consent fields, and persist registration fingerprints. The highest backup ID survives; archived copies expire after 90 days.
2. Publish an active global `policy` announcement with readable content in the requested or default locale. Registration returns `registration_policy_unavailable` when no usable policy exists. Health reports this as `degraded` with HTTP 200.
3. Keep Laravel's scheduler running. `passkey-backups:cleanup-archive` runs daily and safely skips installations without the archive table.
4. For OIDC, retain the registered `/req/login` callback and use `SESSION_SAME_SITE=lax`. Login starts through a document navigation to `/auth/redirect`.
5. Set `HAWKI_SPA_AUTH=true` to send the legacy `/login`, `/handshake`, and `/register` entry routes to the SPA. Refresh Laravel's configuration cache when changing this setting. The default is `false`, so switching those entry routes remains an explicit rollout step.

## Route access

Routes declare `meta.access` as `public`, `server-session`, or `crypto-ready`. The default, including the fallback route, is `crypto-ready`. `meta.chrome: 'none'` hides the application sidebar on auth pages.

Authentication and decrypted key availability are separate: `app.isAuthenticated` reports the server identity; `app.cryptoReady` becomes true only after passkey validation, `after_passkey` migrations, and keychain loading. `after_login` migrations run during the bootstrap migration stage. Guest boots skip them.

The `next` query parameter accepts only validated paths under `/new/`. It travels through login and setup and is followed after the keychain is ready. A manual reset discards it.

## Registration and recovery

Registration submits policy consent, encrypted keys, and the encrypted backup in one transaction. A repeated identical payload returns success without rewriting keys; a different payload after completion returns `registration_already_completed`. If the policy changes, the browser requests fresh consent while retaining the generated key material. It stores the encrypted local passkey before reloading into the handshake.

The server remembers when a registering session enters the SPA, including direct `/new` access while the rollout flag is disabled. Those sessions cannot use the legacy completion or backup-write endpoints. Legacy completion also refuses an account that another session has already registered.

An inconsistent keychain leads to an explanation and an explicit reset action. It never triggers an automatic clean write. Backup recovery reads the JSON:API resource `/api/hawki/v1/passkey-backups/me` through `app.restApi.getResource('passkey-backups', 'me')`, decrypts locally, and follows the same validation and migration sequence as manual passkey entry. Other resource IDs never expose another user's backup.

Logout locks the keychain and clears decrypted keys from memory before contacting the API. The encrypted passkey remains in browser storage, scoped to the account username. After the same account signs in again, the browser restores that passkey and validates it against the server keychain before unlocking. A different account never restores the previous account's passkey. Profile resets and explicit local passkey removal still delete the stored copy. A failed request leaves a retry screen, and background session checks cannot interrupt that flow. For other API requests, HTTP 401 or 419 during an established login or registration session locks the in-memory keychain while retaining the encrypted browser passkey and returns to login, including when the current page is a handshake or setup page. The redirect preserves the original validated destination rather than making the auth page itself the next destination. Invalid credentials submitted by a guest remain a login-form error.

## Verification

Run `bin/env npm run check`, `bin/env npm run test:auth`, and the PHP auth, registration, keychain, and health tests through `bin/env`. Ensure `APP_ENV=testing` in the PHPUnit process; a container-provided `APP_ENV=local` otherwise keeps CSRF validation enabled in the feature tests. The feature tests use an isolated in-memory SQLite database. A deployment should additionally exercise the configured identity provider's login callback and logout return URL.
