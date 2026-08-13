# External App Integration

:::warning[Not fully implemented]
The external app integration is not completely implemented yet. The design and flow are documented here for reference and planning. See [Technical Debt](../900-Technical-Debt.md) for status.
:::

HAWKI can be embedded in third-party applications that need to access its API on behalf of their own users. The ext-app OAuth-like flow creates a one-time, cryptographically verified bridge between an external user identity and a HAWKI account.

:::caution[Two separate access mechanisms]
HAWKI has **two distinct** API access mechanisms that are easily confused:

1. **Personal access tokens** — individual users create Sanctum bearer tokens via the profile UI or the `app:token` artisan command (`ApiTokenService`). Simple long-lived tokens tied to a single HAWKI user.
2. **External app integration** — a third-party application is registered once via `ext-app:create`. It uses asymmetric cryptography to sign per-user connection requests. This is the mechanism described on this page.

Keep them separate.
:::

## What it is for

An external application (a university LMS, a custom portal) wants to give its own users access to HAWKI group chats or AI features without requiring those users to log in to HAWKI separately. The ext-app flow creates a one-time, cryptographically verified bridge between an external user identity and a HAWKI account.

## Feature flags

All ext-app behaviour is controlled by `config/external_access.php`. Open the config file for the canonical key list. The load-bearing ones:

- `ALLOW_EXTERNAL_COMMUNICATION` (master switch for all external API access)
- `ALLOW_USER_TOKEN_CREATION` (let users create personal tokens via the UI)
- `ALLOW_EXTERNAL_APPS` (enable ext-app registration flow; requires the master switch)
- `ALLOW_EXTERNAL_APPS_GROUPS_AI` (allow AI `@hawki` handle in ext-app group chats)
- `ALLOW_EXTERNAL_APPS_CONNECT_REQUEST_TIMEOUT` (seconds a signed connect request stays valid, default 900 / 15 min)

`ALLOW_EXTERNAL_APPS` requires `ALLOW_EXTERNAL_COMMUNICATION` to also be `true`. If you enable ext-app integration, it is strongly recommended to also enable `ALLOW_USER_TOKEN_CREATION` so users can revoke app-issued tokens from the profile UI.

## App registration — `ExtAppCreator`

`App\Services\ExtApp\ExtAppCreator` is called once per external application via the `ext-app:create` artisan command. In a single DB transaction it:

1. Creates a dedicated HAWKI system user for the app (`APP: {app-name}@app.hawki.org`).
2. Generates a Sanctum API token for that system user — the **app API token**.
3. Generates an RSA keypair (`AsymmetricCrypto::generateKeypair()`). Only the public key is stored in the `ExtApp` record. **The private key is printed to the console once and never stored.**
4. Persists an `ExtApp` record with `name`, `redirect_url`, optional `url`, `description`, `logo_url`, and the stored `app_public_key`.

The operator receives the app API token and the RSA private key at the end of `ext-app:create`. Both must be stored securely by the external application — they cannot be retrieved again.

## OAuth-like connection flow

Once an app is registered, the flow for each new end-user connection:

```
External app                         HAWKI backend
─────────────────────────────────────────────────────────────────────
1. App authenticates to HAWKI using the app API token (Bearer).

2. App requests GET /api/hawki/v1/connections/{extUserId}
   HAWKI detects unknown extUserId → returns EXTERNAL_APP connection
   with an encrypted `extAppConnectRequest` payload.

3. App redirects the end-user's browser to HAWKI's /connect page
   and forwards the encrypted payload.

4. User authenticates to HAWKI (LDAP / OIDC / etc.).
   Browser calls POST to complete the connection:
   - decrypts the payload, validates the HMAC signature
   - creates an ExtAppUser record linking extUserId → HAWKI User
   - generates a per-user RSA keypair
   - creates a user-scoped API token
   - encrypts both the private key and the token with the APP's RSA public key
   - stores everything in ext_app_users

5. HAWKI redirects the user back to the app's redirect_url.

6. App exchanges the connect request string for the user's secrets
   by calling GET /api/hawki/v1/connections/{extUserId} again.
   HAWKI now recognises the extUserId → returns EXTERNAL_APP_AUTHENTICATED
   connection with encrypted secrets (passkey, apiToken, privateKey).

7. App decrypts the secrets using its RSA private key.
   From now on the app makes API calls using the user's API token.
```

## Key classes

- **`ConnectRequestCrypto`** (`App\Services\ExtApp\ConnectRequestCrypto`) — creates and validates the signed payload. `encryptPayload(ExtAppConnectRequestPayload, ExtApp)` serialises the payload, adds a UTC expiry timestamp, computes an HMAC validator (SHA-256 of sorted payload + `APP_KEY` + `app_public_key`), and AES-256-GCM-encrypts the whole thing. `decryptPayload(string)` reverses it, checks expiry, recomputes and compares the HMAC. Returns `null` if anything is wrong or the payload has expired. The timeout is read from `config/external_access.php`. The wire format is `HybridCryptoValue` (see [Encryption](../400-Domains/400-Encryption/410-Encryption.md)).
- **`ExtAppUserConnector`** (`App\Services\ExtApp\ExtAppUserConnector`) — `connect(User, passkey, connectRequestString)` is called when a HAWKI user completes the browser-side connection step. Validates the connect request, generates a per-user RSA keypair, creates a Sanctum token, encrypts both the private key and the token with the app's RSA public key (using `HybridCrypto`), and persists an `ExtAppUser` record.

## Connection types

The `connections` JSON:API resource uses `EXTERNAL_APP` (external user not yet linked to a HAWKI account) and `EXTERNAL_APP_AUTHENTICATED` (linked and has credentials). See [Connection Bootstrap](../300-HTTP-API/200-Connection-Bootstrap.md) for the full `ConnectionType` enum.

## Artisan commands

`ext-app:create`, `ext-app:list`, `ext-app:remove` — see [Artisan Commands](../500-Reference/100-Artisan-Commands.md) for the full command reference including personal-token commands (`app:token`).
