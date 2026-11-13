# Upgrading to version %%VERSION%%

## Overview

Authentication now runs in the Svelte app under `/new/auth/*`. The legacy Blade
gateway (old `/login`, `/register`, `/handshake` pages) is disabled with this
upgrade. Two adjustments at your identity provider are required **before** the
switch, otherwise nobody can log in.

## Steps

### 1. OIDC: whitelist the SPA login URL

Add the following to your IdP client registration's allowed redirect URIs:

```
https://<your-host>/auth/redirect
```

### 2. Shibboleth: protect the SPA login route

Shibboleth attributes are only injected on Apache-protected paths. Add this
block next to your existing `/req/login` block in `shib.conf`, then restart
Apache:

```apacheconf
<Location /auth/redirect>
    AuthType shibboleth
    ShibRequestSetting requireSession 1
    Require valid-user
</Location>
```

### 3. Publish the usage policy

Registration now requires consent to a usage policy. If no `policy`-type
announcement is currently in effect, publish one:

```bash
php artisan announcement:publish
```

### 4. Switch to the SPA gateway

```dotenv
HAWKI_SPA_AUTH=true
```

Then refresh Laravel's configuration cache. LDAP-only installations can skip
steps 1 and 2.

## Notes

- Visitors with a still-open legacy tab during the switch may complete a
  half-old, half-new registration once; they will simply be asked to accept
  the policy again. Keep the switch-over window short.
- Optional: `APP_SECURITY_PASSKEY_AUTO_GENERATE=true` lets the browser
  generate registration passkeys (users get a downloadable backup code)
  instead of asking them to choose one.
