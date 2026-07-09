# External App Integration

HAWKI can be embedded in third-party applications that need to access its API on behalf of their
own users. This article documents the external app (ext-app) OAuth-like flow and its associated
classes.

:::caution[Two separate access mechanisms]
HAWKI has **two distinct** API access mechanisms that are easily confused:

1. **Personal access tokens** — individual users create Sanctum bearer tokens via the profile
   UI or the `app:token` artisan command (`ApiTokenService`). These are simple long-lived
   tokens tied to a single HAWKI user.

2. **External app integration** — a third-party application is registered once via
   `ext-app:create`. It then uses asymmetric cryptography to sign per-user connection
   requests. This is the mechanism described in this article.

The old `11-HAWKI_API.md` documentation conflates both mechanisms. New code and documentation
must keep them separate.
:::

---

## What Ext-App Integration Is For

An external application (for example, a university LMS or a custom portal) wants to give its
own users access to HAWKI group chats or AI features without requiring those users to log in to
HAWKI separately. The ext-app flow creates a one-time, cryptographically verified bridge between
an external user identity and a HAWKI account.

---

## Feature Flags

All ext-app behaviour is controlled by `config/external_access.php`. Configure these in `.env`:

| Key | Env variable | Default | Effect |
|---|---|---|---|
| `enabled` | `ALLOW_EXTERNAL_COMMUNICATION` | `false` | Master switch for all external API access |
| `allow_user_token` | `ALLOW_USER_TOKEN_CREATION` | `false` | Let users create personal tokens via the UI |
| `apps` | `ALLOW_EXTERNAL_APPS` | `false` | Enable ext-app registration flow |
| `apps_groups_ai` | `ALLOW_EXTERNAL_APPS_GROUPS_AI` | `true` | Allow AI `@hawki` handle in ext-app group chats |
| `app_connect_request_timeout` | `ALLOW_EXTERNAL_APPS_CONNECT_REQUEST_TIMEOUT` | `900` (15 min) | Seconds a signed connect request stays valid |

:::note
`ALLOW_EXTERNAL_APPS` requires `ALLOW_EXTERNAL_COMMUNICATION` to also be `true`. If you enable
ext-app integration, it is strongly recommended to also enable `ALLOW_USER_TOKEN_CREATION` so
that users can revoke app-issued tokens from the profile UI.
:::

---

## App Registration: ExtAppCreator

`App\Services\ExtApp\ExtAppCreator` is called once per external application.

```
ext-app:create
```

It performs the following in a single DB transaction:

1. Creates a dedicated HAWKI system user for the app (`APP: {app-name}@app.hawki.org`).
2. Generates a Sanctum API token for that system user — this is the **app API token**.
3. Generates an RSA keypair (`AsymmetricCrypto::generateKeypair()`). Only the public key is
   stored in the `ExtApp` record. **The private key is printed to the console once and never
   stored.**
4. Persists an `ExtApp` record with: `name`, `redirect_url`, optional `url`, `description`,
   `logo_url`, and the stored `app_public_key`.

The operator receives the app API token and the RSA private key at the end of `ext-app:create`.
Both must be stored securely by the external application — they cannot be retrieved again.

---

## OAuth-Like Connection Flow

Once an app is registered, the flow for each new end-user connection is:

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

---

## Key Classes

### ConnectRequestCrypto

`App\Services\ExtApp\ConnectRequestCrypto` creates and validates the signed payload that travels
between the external app and HAWKI during the connection handshake.

- `encryptPayload(ExtAppConnectRequestPayload, ExtApp)` — serialises the payload, adds a UTC
  expiry timestamp, computes an HMAC validator (SHA-256 of the sorted payload + `APP_KEY` +
  `app_public_key`), and AES-256-GCM-encrypts the whole thing. The result is base64-encoded for
  transport.
- `decryptPayload(string)` — reverses the above: decodes, decrypts, checks expiry, recomputes
  and compares the HMAC. Returns `null` if anything is wrong or the payload has expired.

The timeout is read from `config/external_access.php app_connect_request_timeout` (default 15
minutes).

### ExtAppCreator

See "App Registration" above.

### ExtAppUserConnector

`App\Services\ExtApp\ExtAppUserConnector::connect(User, passkey, connectRequestString)` — called
when a HAWKI user completes the browser-side connection step:

1. Validates the connect request string via `ConnectRequestCrypto::decryptPayload()`.
2. If the external user is already linked, returns the existing `ExtAppUser` record.
3. Otherwise: generates a per-user RSA keypair, creates a Sanctum token for the HAWKI user,
   encrypts both the private key and the token with the **app's** RSA public key (using
   `HybridCrypto`), and persists an `ExtAppUser` record.

---

## Connection Types

The `connections` JSON:API resource uses different `ConnectionType` values for ext-app users:

| ConnectionType | When returned | Frontend narrowing function |
|---|---|---|
| `EXTERNAL_APP` | External user is not yet linked to a HAWKI account | `getConnection()` only |
| `EXTERNAL_APP_AUTHENTICATED` | External user is linked and has credentials | `getConnection()` only |

For the full five-value `ConnectionType` table, see
[Connection Bootstrap](../400-Connection-Bootstrap.md).

---

## Artisan Commands

| Command | Purpose |
|---|---|
| `ext-app:create` | Register a new external app; prints API token + RSA private key (one-time) |
| `ext-app:list` | List all registered external apps |
| `ext-app:remove` | Remove an external app and its associated user |

See [Artisan Commands](../1000-Infrastructure/200-Artisan-Commands.md) for the full command
reference including personal-token commands (`app:token`).
