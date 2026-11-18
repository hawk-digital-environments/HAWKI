# Upgrading to version %%VERSION%%

## Overview

Authentication now runs in the Svelte app under `/new/auth/*`. The legacy Blade
gateway (old `/login`, `/register`, `/handshake` pages) is disabled with this
upgrade. No identity-provider changes are required — the IdP callback stays on
the existing `/req/login`.

## Steps

### 1. Publish the usage policy

Registration now requires consent to a usage policy. If no `policy`-type
announcement is currently in effect, publish one:

```bash
php artisan announcement:publish
```

### 2. Switch to the SPA gateway

```dotenv
HAWKI_SPA_AUTH=true
```

Then refresh Laravel's configuration cache.

## Notes

- Visitors with a still-open legacy tab during the switch may complete a
  half-old, half-new registration once; they will simply be asked to accept
  the policy again. Keep the switch-over window short.
- Optional: `APP_SECURITY_PASSKEY_AUTO_GENERATE=true` lets the browser
  generate registration passkeys (users get a downloadable backup code)
  instead of asking them to choose one.
