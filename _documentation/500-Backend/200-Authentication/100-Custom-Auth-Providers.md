# Custom Auth Providers

This page documents each built-in authentication provider and explains how to implement a custom one.

:::note
The class names here are the actual source files. Some older documentation and aspirational refactor sketches refer to an `AuthProviderInterface` layer that does not exist in the current codebase. The real contract is `AuthServiceInterface`, which each provider implements directly.
:::

## Built-in Providers

### LdapService

**File:** `app/Services/Auth/LdapService.php`  
**Config:** `config/ldap.php`  
**Implements:** `AuthServiceInterface`, `AuthServiceWithCredentialsInterface`

`LdapService` performs a two-step LDAP bind: it first binds with a service account to locate the user's DN, then re-binds with the user's own credentials to validate the password.

Key value objects built from config:

| Value Object | Role |
|---|---|
| `LdapConnectUri` | Wraps host + port, validates the connection URI |
| `LdapBindCredentials` | Holds the service-account bind DN and password |
| `LdapFilterArgs` | Holds the base DN and search filter template; `getFilterForUser($username)` interpolates the username into the filter |
| `LdapAttributeReader` | Maps LDAP entry attributes to `AuthenticatedUserInfo` fields |

Relevant `.env` variables:

```ini
LDAP_HOST=ldap.example.com
LDAP_PORT=389
LDAP_BIND_DN=cn=serviceaccount,dc=example,dc=com
LDAP_BIND_PW=secret
LDAP_BASE_DN=dc=example,dc=com
LDAP_SEARCH_DN=ou=users,dc=example,dc=com   # overrides LDAP_BASE_DN for search
LDAP_FILTER=(uid=%s)

LDAP_ATTR_USERNAME=cn      # default
LDAP_ATTR_EMAIL=mail       # default
LDAP_ATTR_NAME=displayname # default
LDAP_ATTR_EMPLOYEETYPE=employeetype # default
LDAP_INVERT_NAME=true      # swap first/last name order
```

The PHP `ldap` extension must be installed and enabled. `LdapService` logs a descriptive error and throws `AuthFailedException` if the extension is missing.

:::tip
`LDAP_SEARCH_DN` and `LDAP_BASE_DN` have overlapping historical semantics for backward compatibility. Use `LDAP_SEARCH_DN` for new installations — it is unambiguous.
:::

### OidcService

**File:** `app/Services/Auth/OidcService.php`  
**Config:** `config/open_id_connect.php`  
**Implements:** `AuthServiceInterface`, `AuthServiceWithLogoutRedirectInterface`

`OidcService` runs a standard OpenID Connect authorization-code flow using `jumbojett/openid-connect-php`. On the first request (no authorization code present) it redirects the browser to the IdP. On the callback (authorization code in query string) it exchanges the code for tokens, verifies the ID token, and reads user claims.

Key config keys:

```ini
OIDC_IDP=https://idp.example.com
OIDC_CLIENT_ID=hawki
OIDC_CLIENT_SECRET=secret
OIDC_LOGOUT_URI=https://idp.example.com/logout
OIDC_SCOPES=profile,email
OIDC_PKCE_METHOD=S256     # or leave empty to disable PKCE

OIDC_USERNAME_VAR=preferred_username  # default
OIDC_EMAIL_VAR=email                  # default
OIDC_EMPLOYEETYPE_VAR=employeetype    # default

# Display name: either a single claim or a comma-separated list to concatenate
OIDC_NAME_VAR=name
# Or use legacy first+last name:
# OIDC_FIRSTNAME_VAR=given_name
# OIDC_LASTNAME_VAR=family_name
```

When `getLogoutResponse()` is called, `OidcService` builds a redirect URL to the IdP's logout endpoint with a `post_logout_redirect_uri` parameter pointing back to HAWKI.

### ShibbolethService

**File:** `app/Services/Auth/ShibbolethService.php`  
**Config:** `config/shibboleth.php`  
**Implements:** `AuthServiceInterface`, `AuthServiceWithLogoutRedirectInterface`

`ShibbolethService` does not perform any network calls itself. It reads identity from HTTP server variables (`$_SERVER`) that the Shibboleth SP module injects. The web server must be configured to pass the appropriate attributes to PHP (check both the plain name and the `REDIRECT_`-prefixed form — Shibboleth sometimes uses the prefix).

If the expected username attribute is absent, `ShibbolethService` redirects the browser to `SHIBBOLETH_LOGIN_URL` for the SP to initiate authentication.

Key `.env` variables:

```ini
SHIBBOLETH_LOGIN_URL=/Shibboleth.sso/Login    # default
SHIBBOLETH_LOGOUT_URL=/Shibboleth.sso/Logout  # default

SHIBBOLETH_USERNAME_VAR=REMOTE_USER  # default
SHIBBOLETH_EMAIL_VAR=mail            # default
SHIBBOLETH_EMPLOYEETYPE_VAR=employee # default
SHIBBOLETH_NAME_VAR=displayname      # default; may be comma-separated for concatenation
```

### TestAuthService

**File:** `app/Services/Auth/TestAuthService.php`  
**Config:** `config/test_users.php`  
**Implements:** `AuthServiceInterface`, `AuthServiceWithCredentialsInterface`

`TestAuthService` authenticates against a static list of users defined in `config/test_users.php`. It is intended for local development and automated testing only. Enable it via:

```ini
TEST_USERS_ACTIVE=true
```

When active and the main provider supports credentials, `TestAuthService` is automatically prepended to the chain so test accounts can log in without touching the real LDAP/OIDC/Shibboleth backend.

:::warning
Never enable `TEST_USERS_ACTIVE` in production. The test users bypass all real identity checks.
:::

## Implementing a Custom Provider

To add a new authentication backend:

### 1. Implement AuthServiceInterface

```php
namespace App\Services\Auth;

use App\Services\Auth\Contract\AuthServiceInterface;
use App\Services\Auth\Value\AuthenticatedUserInfo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MyCustomAuthService implements AuthServiceInterface
{
    public function authenticate(Request $request): AuthenticatedUserInfo|Response
    {
        // Check the request for whatever credentials your provider uses.
        // Return AuthenticatedUserInfo on success.
        // Return a Response (redirect) if the user needs to visit an external URL to authenticate.
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

Optionally implement the mixin interfaces if your provider needs them:

- `AuthServiceWithCredentialsInterface` — if users supply a username/password on a HAWKI login form.
- `AuthServiceWithLogoutRedirectInterface` — if logout must redirect to an external IdP.
- `AuthServiceWithPostProcessingInterface` — if you need hooks after the local user record is resolved.

### 2. Register the provider

Point `AUTHENTICATION_METHOD` at your class:

```ini
AUTHENTICATION_METHOD=App\Services\Auth\MyCustomAuthService
```

The container resolves the class normally, so constructor injection works as expected:

```php
class MyCustomAuthService implements AuthServiceInterface
{
    public function __construct(
        #[Config('my_provider.api_url')]
        private string $apiUrl,
        private LoggerInterface $logger,
    ) {}
}
```

### 3. Add a config file (optional)

If your provider requires its own config keys, create `config/my_provider.php` and add the corresponding `.env` variables. Follow the pattern of `config/ldap.php`, `config/open_id_connect.php`, or `config/shibboleth.php` for reference.

:::tip
For providers that redirect to an external IdP, implement `AuthServiceWithLogoutRedirectInterface` to ensure users are also logged out at the IdP when they sign out of HAWKI.
:::
