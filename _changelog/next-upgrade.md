# Upgrading to version %%VERSION%%

## Overview

This release ships the new SPA authentication (`/new/auth/*`) with CRUD JSON:API
endpoints for login, registration and session management, and the registration
policy consent flow on top of the announcements subsystem. The legacy Blade
gateway keeps working unchanged; the SPA takes over per deployment through the
new `HAWKI_SPA_AUTH` flag. Two identity-provider configuration adjustments are
required **before** enabling that flag.

## Steps

### 1. Update dependencies and rebuild

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
npm install
npm run build
```

### 2. Run database migrations

```bash
php hawki migrate
```

Four new migrations run: the passkey-backup archive (duplicates from before the
unique index are moved to `passkey_backup_archives` and kept for 90 days), a
unique index on `passkey_backups.username`, the policy consent columns
(`locale`, `content_hash`) on `announcement_user`, and the registration
fingerprint on `users`. No data is lost; a daily scheduled cleanup
(`passkey-backups:cleanup-archive`) prunes expired archive rows.

### 3. Publish a registration policy (required for SPA registration)

The SPA registration flow requires a published usage policy, and consent is
recorded against the policy's content hash. If no `type: policy` announcement
is currently in effect, publish one before enabling the SPA:

```bash
php artisan announcement:publish
```

Policies are validated at publish time: two policies may never overlap, and a
policy must have content files for the current or default locale under
`resources/announcements/<view>/<locale>.md`. Changing a policy's wording after
publication requires publishing a new policy — users who consented to the old
wording will be asked to consent again (this is intentional; see Notes).

### 4. OIDC deployments: whitelist the new redirect URI

The SPA starts redirect-mode logins at `/auth/redirect`, and the OIDC client now
pins `redirect_uri` to the route that started the flow. Add the following URL
to your IdP client registration's allowed redirect URIs:

```
https://<your-host>/auth/redirect
```

The existing `/req/login` redirect URI must stay whitelisted while the legacy
gateway is enabled. Login through the SPA fails at the IdP with a
`redirect_uri` error if this step is skipped.

### 5. Shibboleth deployments: protect the new login route

Shibboleth attributes are only injected on Apache-protected paths. Extend the
existing `<Location>` block in your `shib.conf` (e.g.
`/etc/httpd/conf.d/shib.conf`) so the SPA login route is covered as well:

```apacheconf
<Location /auth/redirect>
    AuthType shibboleth
    ShibRequestSetting requireSession 1
    Require valid-user
</Location>
```

Keep the `/req/login` block while the legacy gateway is enabled, then restart
Apache. This is the same kind of adjustment as the earlier
`/req/login-shibboleth` → `/req/login` migration.

### 6. Enable the SPA authentication (when ready)

```dotenv
HAWKI_SPA_AUTH=true
```

With the flag enabled, `/login`, `/register` and `/handshake` redirect into the
SPA (`/new/auth/*`); with it disabled, the legacy Blade gateway serves exactly
as before. Refresh Laravel's configuration cache after changing the flag.

Optional companion setting — browser-generated passkeys during registration
instead of user-chosen ones (affects the SPA registration flow):

```dotenv
APP_SECURITY_PASSKEY_AUTO_GENERATE=false
```

## Notes

- **New API endpoints** (all under `/api/hawki/v1`): `GET /auth/hawki`
  (provider descriptor; the redirect start URL is exposed as `links.redirect`),
  `POST /sessions` (login), `DELETE /sessions/hawki` (logout),
  `GET /registrations/hawki` (registration policy + consent state) and
  `POST /registrations` (registration completion), `GET /passkey-backups/me`.
  The old `/req/*` endpoints remain functional for the legacy gateway.
- **Registration consent is hash-bound.** Each user's consent records the
  content hash of the exact policy text they accepted. Editing a policy's
  markdown files without publishing a new policy announcement leaves stored
  consents "stale" and users will be asked to consent again — publish a new
  policy instead of editing a live one.
- **Mixed UI warning:** sessions that started a login in the SPA but completed
  registration through a stale legacy tab are not blocked; such users are
  simply asked to consent to the policy again later. Keep the transition
  window short.
- **Credential login (LDAP) needs no IdP changes** — steps 4 and 5 only apply
  to redirect-based providers (OIDC, Shibboleth).
