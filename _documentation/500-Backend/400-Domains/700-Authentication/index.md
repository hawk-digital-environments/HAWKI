# Authentication

HAWKI supports multiple authentication backends simultaneously. A single HTTP request is tested against each configured provider in order — the first one that succeeds wins. All providers share the same contract, so the rest of the application never needs to know which backend authenticated a given user.

Authentication here means the **login procedure** — how a user proves their identity to HAWKI (LDAP, OIDC, Shibboleth, test users). The actual session/token transport is handled by Laravel Sanctum (stateful session cookies for the SPA, bearer tokens for external apps) and is not custom code — see [Sanctum sessions and personal access tokens](#sanctum-sessions-and-personal-access-tokens) below.

For configuring the built-in providers (env variables, LDAP connection parameters, OIDC client setup, Shibboleth SP configuration), see [Configuration → Authentication](../../../200-Configuration/400-Authentication.md).

## The contract

Every authentication provider implements `AuthServiceInterface` (`app/Services/Auth/Contract/AuthServiceInterface.php`):

```php
public function authenticate(Request $request): AuthenticatedUserInfo|Response;
```

On success it returns an `AuthenticatedUserInfo` value object. On a redirectable failure (for example, OIDC or Shibboleth needing to redirect the browser to an IdP) it returns a `Response` directly. On an internal error it throws `AuthFailedException`.

### Optional mixin interfaces

Providers that need extra capabilities implement one or more companion interfaces:

| Interface | What it adds |
|---|---|
| `AuthServiceWithCredentialsInterface` | `useCredentials(username, password)` / `forgetCredentials()` — for providers that receive username+password from a login form (LDAP, test stub) |
| `AuthServiceWithLogoutRedirectInterface` | `getLogoutResponse(Request)` — for providers that need to redirect the browser to an IdP on logout (OIDC, Shibboleth) |
| `AuthServiceWithPostProcessingInterface` | `afterLoginWithUser(User, Request)` / `afterLoginWithoutUser(AuthenticatedUserInfo, Request)` — hooks fired after the local user record is resolved or created |

## ChainedAuthService

`ChainedAuthService` is the concrete implementation registered as `AuthServiceInterface`. It holds an ordered list of provider instances and calls `authenticate()` on each in turn. When a provider throws `AuthFailedException`, the chain moves on. If all providers fail, a final `AuthFailedException` is raised.

The chain is assembled in `AuthServiceProvider::register()` and driven by a single config key:

```ini
# .env
AUTHENTICATION_METHOD=LDAP   # or: Shibboleth, OIDC, or a fully qualified class name
```

Legacy string values (`LDAP`, `Shibboleth`, `OIDC`) are mapped to their service classes automatically. You can also set `AUTHENTICATION_METHOD` to any fully-qualified class name that implements `AuthServiceInterface`.

When `config/test_users.php` has `TEST_USERS_ACTIVE=true` and the main service supports credentials, `ChainedAuthService` is automatically inserted to try the test service first, falling back to the configured real provider.

:::caution
The automatic chaining is only for the test stub. Configuring custom chains requires an `AuthServiceProvider` override; do not use `AUTHENTICATION_METHOD=SomeClass` for that purpose.
:::

## Built-in providers

- **`LdapService`** (`app/Services/Auth/LdapService.php`, config `config/ldap.php`) — two-step LDAP bind: service-account bind to locate the user DN, then re-bind with the user's credentials. Implements `AuthServiceWithCredentialsInterface`.
- **`OidcService`** (`app/Services/Auth/OidcService.php`, config `config/open_id_connect.php`) — standard OIDC authorization-code flow using `jumbojett/openid-connect-php`. Redirects to IdP on first request, exchanges code on callback. Implements `AuthServiceWithLogoutRedirectInterface`.
- **`ShibbolethService`** (`app/Services/Auth/ShibbolethService.php`, config `config/shibboleth.php`) — reads identity from HTTP server variables injected by the Shibboleth SP module; performs no network calls itself. Implements `AuthServiceWithLogoutRedirectInterface`.
- **`TestAuthService`** (`app/Services/Auth/TestAuthService.php`, config `config/test_users.php`) — authenticates against a static user list for local dev and tests. Never enable in production.

The `.env` variables for each provider are documented in their config files. Open the config file for the canonical list.

## Implementing a custom provider

```php
namespace App\Services\Auth;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MyCustomAuthService implements AuthServiceInterface
{
    public function __construct(
        #[Config('my_provider.api_url')]
        private string $apiUrl,
        private LoggerInterface $logger,
    ) {}

    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        // Return AuthenticatedUserInfo on success.
        // Return a Response (redirect) if the user needs to visit an external URL.
        // Throw AuthFailedException for any failure.
        return new AuthenticatedUserInfo(
            username: $resolvedUsername,
            displayName: $resolvedDisplayName,
            email: $resolvedEmail,
            employeeType: 'employee',
        );
    }
}
```

Register it:

```ini
AUTHENTICATION_METHOD=App\Services\Auth\MyCustomAuthService
```

The container resolves the class normally, so constructor injection works as expected. Optionally implement the mixin interfaces if your provider needs credentials, logout redirect, or post-login hooks.

### Composing a display name from multiple attributes

When a provider needs to compose a display name from multiple IdP attributes (for example, concatenating `givenName` and `sn` from LDAP), use `DisplayNameBuilder::build()`:

```php
$name = DisplayNameBuilder::build(
    'givenName,sn',            // comma-separated list of attribute names
    fn(string $field) => $ldapAttributes[$field][0] ?? null,
    $this->logger
);
```

Attributes that resolve to an empty string are silently skipped. If none resolve, an exception is thrown.

## Registration flow

When a recognised user logs in for the first time, HAWKI does not yet have a local database record for them. The auth middleware detects this and sets `UserContext` to `REGISTERING_USER` instead of `USER` (see [Request Contexts](../../200-Concepts/120-Request-Contexts.md)). The connection bootstrap reports `INTERNAL_REGISTERING_USER` to the frontend.

The key-generation step — creating the user's asymmetric keypair, storing the public key, completing the registration — happens in the `/handshake` Blade view flow, not through the JSON:API layer. See [Connection Bootstrap](../../300-HTTP-API/200-Connection-Bootstrap.md).

## Sanctum sessions and personal access tokens

Session management and WebSocket channel authentication use Laravel Sanctum (`config/sanctum.php`). Sanctum issues the session cookies that subsequent JSON:API requests carry.

Personal access tokens (managed via `ApiTokenService` and the `app:token` artisan command) are a **separate** Sanctum mechanism from the ext-app OAuth-like flow. See [External App Integration](../../700-Roadmap/300-External-Apps.md) for the distinction.
