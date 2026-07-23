# Authentication

HAWKI supports multiple authentication backends simultaneously. A single HTTP request is tested against each configured provider in order — the first one that succeeds wins. All providers share the same contract, so the rest of the application never needs to know which backend authenticated a given user.

:::note
This section is placed before the JSON:API reference because auth concepts — authorization, user identity, connection type — appear immediately in the API documentation.
:::

## AuthServiceInterface

Every authentication provider must implement one method:

```php
public function authenticate(Request $request): AuthenticatedUserInfo|Response;
```

On success it returns an `AuthenticatedUserInfo` value object. On a redirectable failure (for example, Shibboleth or OIDC needing to redirect the browser to an IdP) it returns a `Response` directly. On an internal error it throws `AuthFailedException`.

The full contract lives at `app/Services/Auth/Contract/AuthServiceInterface.php`.

### Optional mixin interfaces

Providers that need extra capabilities implement one or more companion interfaces:

| Interface | What it adds |
|---|---|
| `AuthServiceWithCredentialsInterface` | `useCredentials(username, password)` / `forgetCredentials()` — for providers that receive username+password from a login form (LDAP, test stub) |
| `AuthServiceWithLogoutRedirectInterface` | `getLogoutResponse(Request)` — for providers that need to redirect the browser to an IdP on logout (OIDC, Shibboleth) |
| `AuthServiceWithPostProcessingInterface` | `afterLoginWithUser(User, Request)` / `afterLoginWithoutUser(AuthenticatedUserInfo, Request)` — hooks fired after the local user record is resolved or created |

## ChainedAuthService

`ChainedAuthService` is the concrete implementation that the container registers as `AuthServiceInterface`. It holds an ordered list of provider instances and calls `authenticate()` on each in turn. When a provider throws `AuthFailedException`, the chain moves on to the next. If all providers fail, a final `AuthFailedException` is raised.

The chain is assembled in `AuthServiceProvider::register()`. The active provider is driven by a single config key:

```
# .env
AUTHENTICATION_METHOD=LDAP   # or: Shibboleth, OIDC, or a fully qualified class name
```

Legacy string values (`LDAP`, `Shibboleth`, `OIDC`) are mapped to their service classes automatically. You can also set `AUTHENTICATION_METHOD` to any fully-qualified class name that implements `AuthServiceInterface`.

When `config/test_users.php` has test users enabled (`TEST_USERS_ACTIVE=true`) and the main service supports credentials, `ChainedAuthService` is automatically inserted to try the test service first, falling back to the configured real provider.

```
chain order (test mode): TestAuthService → LdapService
chain order (normal):    LdapService (or whichever is configured)
```

:::caution
Do not use the old `AUTHENTICATION_METHOD=SomeClass` approach to configure multiple simultaneous providers. The chaining is only automatic for the test stub. Configuring custom chains requires a `AuthServiceProvider` override.
:::

## AuthenticatedUserInfo

A simple `readonly` value object returned by a successful `authenticate()` call:

| Property | Type | Description |
|---|---|---|
| `username` | `string` | Unique identifier within the auth system (e.g. email or uid). **Must be unique across all users.** |
| `displayName` | `string` | Human-friendly name shown in the UI. |
| `email` | `string` | Email address. **Must be unique.** |
| `employeeType` | `string` | Group/role identifier used for logging and future permission management. |

## DisplayNameBuilder

When a provider needs to compose a display name from multiple IdP attributes (for example, concatenating `givenName` and `sn`), it uses `DisplayNameBuilder::build()`:

```php
$name = DisplayNameBuilder::build(
    'givenName,sn',            // comma-separated list of attribute names
    fn(string $field) => $ldapAttributes[$field][0] ?? null,
    $this->logger
);
```

Any attributes that resolve to an empty string are silently skipped. If none resolve, an exception is thrown.

## Registration Flow

When a recognized user logs in for the first time, HAWKI does not yet have a local database record for them. The authentication middleware detects this and sets `UserContext` to `REGISTERING_USER` instead of `USER`. The connection bootstrap reports this state to the frontend as `INTERNAL_REGISTERING_USER` (see [Connection Bootstrap](../400-Connection-Bootstrap.md)).

The actual key-generation step — creating the user's asymmetric keypair, storing the public key, completing the registration — happens in the `/handshake` Blade view flow, not through the JSON:API layer. Once the handshake is complete, subsequent logins resolve to an existing user record and set `UserContext` to `USER`.

## Session Management and Sanctum

Session management and WebSocket channel authentication use Laravel Sanctum (`config/sanctum.php`). Sanctum issues the session cookies that subsequent JSON:API requests carry. Personal access tokens (managed via `ApiTokenService` and the `app:token` artisan command) are a separate Sanctum mechanism from the ext-app OAuth-like flow — see [External App Integration](../800-Encryption-and-Security/200-External-Apps.md) for details.

## Passkey Integration

After registration, users may attach a WebAuthn passkey to protect their end-to-end encryption keys. Passkeys are not an authentication method in the normal sense — they are a second-factor mechanism for recovering or re-deriving the user's private key material on a new device. The passkey flow is documented in [Encryption Overview](../800-Encryption-and-Security/index.md).
