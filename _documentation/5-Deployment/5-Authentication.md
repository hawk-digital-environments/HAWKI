---
sidebar_position: 4
---

# Authentication providers

HAWKI supports multiple authentication providers to facilitate user login and management.
The available authentication methods include: LDAP, OpenID Connect (OIDC), and SAML (via Shibboleth).
This document tries to help you set up the authentication method that best fits your needs.

## LDAP

To configure LDAP authentication, first of all you MUST ensure that you have the PHP LDAP extension installed and
enabled [installation instructions](https://www.php.net/manual/en/ldap.installation.php).

#### Setting the environment variables

To configure HAWKI to use LDAP as an authentication provider, set the `AUTHENTICATION_METHOD` environment variable in
your `.env` file to `LDAP`.

Next, you need to configure the LDAP connection parameters. The following environment variables are available:

- `LDAP_HOST` is the hostname or IP address of your LDAP server; something like: "ldap.example.com".
- `LDAP_PORT` is the port number of your LDAP server. Defaults to `389`.
- `LDAP_BASE_DN` is the base distinguished name (DN) for your LDAP directory; something like:
  "dc=example,dc=com". This value is basically the credential you are using to authenticate against an LDAP. We know,
  this SHOULD be the "BIND_DN", but historically we can not change this variable name without breaking existing setups.
- `LDAP_BIND_PW` is the password for the base DN you specified above. _Note of the author: You do not want to know why
  this is called "LDAP_BIND_PW" and not "LDAP_BASE_PW" 🙈._
- `LDAP_SEARCH_DN` is what is **commonly** referred to as the "base DN" or "search base". This is the DN where user
  searches will start; something like: "ou=users,dc=example,dc=com".
- `LDAP_FILTER` is the LDAP search filter to find users. This should be something like
  `(|(sAMAccountName=username)(mail=username))`, which searches for entries with the object class "person" and a "uid"
  attribute matching the provided username. **NOTE** The placeholder `username` will be replaced with the actual
  username provided during login.

After this your connection should be up and running, BUT you still need to tell HAWKI how to interpret the LDAP
attributes.
The following environment variables are available for this and MUST be set according to your LDAP schema:

- `LDAP_ATTR_USERNAME` is the LDAP attribute that contains the username. Defaults to `cn`.
- `LDAP_ATTR_EMAIL` is the LDAP attribute that contains the email address. Defaults to `mail`.
- `LDAP_ATTR_NAME` is the LDAP attribute that contains the full name of the user. Defaults to `displayname`.
- `LDAP_ATTR_EMPLOYEETYPE` is the LDAP attribute that contains the employee type or group identifier. Defaults to
  `employeetype`.

**IMPORTANT** PLEASE NOTE that the `LDAP_BASE_DN` and `LDAP_BIND_PW` are used to authenticate against the LDAP server,
while the `LDAP_SEARCH_DN` is used to search for users! This is a common source of confusion.

## OpenIdConnect (OIDC)

OpenID Connect (OIDC) is a popular authentication protocol built on top of OAuth 2.0.
Hawki supports OIDC as an authentication provider, allowing users to log in using their existing OIDC credentials.

### Configuring your OIDC Provider

When setting up your OIDC provider (e.g., Keycloak, Auth0, Okta, etc.), you need to register HAWKI as a client
application. During this registration process, you will receive a client ID and client secret, which you will need to
configure HAWKI.

You will also be asked to provide a redirect URI for your application. This is the URL where your OIDC provider will
redirect users after they have
successfully authenticated. The redirect URI for HAWKI should be set to: `https://your-hawki-domain/req/login`.

#### Setting the environment variables

To configure HAWKI to use OIDC as an authentication provider, set the `AUTHENTICATION_METHOD` environment variable in
your `.env` file to `OIDC`.

Next, you need to configure the OIDC connection parameters. The following environment variables are available:

- `OIDC_IDP` is the URL of your OIDC Identity Provider (IdP); something like:
  `https://idp.example.com`.
- `OIDC_CLIENT_ID` is the client ID registered with your OIDC IdP. (Provided by your IdP)
- `OIDC_CLIENT_SECRET` is the client secret registered with your OIDC IdP. (Provided by your IdP)
- `OIDC_LOGOUT_URI` is the logout endpoint of your OIDC IdP; that gets called when the user logs out of HAWKI. If
  possible HAWKI will also pass a "post_logout_redirect_uri" and "id_token_hint" parameters to the logout endpoint.
- `OIDC_SCOPES` is a comma separated list of scopes to request during authentication. Defaults to `email,profile`.

As with the other authentication methods, you need to tell HAWKI how to interpret the OIDC claims.
The following environment variables are available for this and MUST be set according to your OIDC provider's
claims:

- `OIDC_USERNAME_VAR` is the OIDC claim that contains the username. Defaults to `preferred_username`.
- `OIDC_EMAIL_VAR` is the OIDC claim that contains the email address. Defaults to `email`.
- `OIDC_EMPLOYEETYPE_VAR` is the OIDC claim that contains the employee type or group identifier.
  This is primarily used for logging purposes, but will, in the future, also be used for permission management.
  Defaults to `employeetype`.
- `OIDC_NAME_VAR` is the OIDC claim that contains the full name of the user for display purposes.
  Historically, this was configured as `OIDC_FIRSTNAME_VAR` and `OIDC_LASTNAME_VAR` for first and last name,
  that were concatenated with a space in between. This is still supported for backward compatibility,
  however it is recommended to use `OIDC_NAME_VAR` instead.
  **Default value rules**: If `OIDC_NAME_VAR` is set, use it; otherwise, if either `OIDC_FIRSTNAME_VAR` or
  `OIDC_LASTNAME_VAR` is set,
  concatenate them; if none of them are set, default to 'preferred_username'.
  If you need to concatenate multiple attributes (e.g. firstname, lastname, title), you can provide a comma-separated
  list of attribute names. If a list is given, the attributes will be concatenated with a space in between. If any of
  them are missing, they will be skipped.

## Shibboleth (SAML)

### Configuring Apache:

If Shibboleth is installed with yum or apt-get, the Apache module `mod_shib` will be installed and activated.
What you need to do next is to determine how the actual service should be protected.

You need to tell Apache, which part should be protected by Shibboleth. In our case it is a simple route
`/req/login` that will trigger the Shibboleth login. You can of course, if you want to protect the entire
site, use `/` as the path. Note, that the latter will make the entire site inaccessible without a valid Shibboleth
session and basically disable the `SHIBBOLETH_LOGIN_URL` in your `.env` file.

In your <VirtualHost> you add a <Location> tag for what you want to protect (found in /etc/httpd/conf.d/shib.conf):

```apacheconf
<Location /req/login>
    AuthType shibboleth
    ShibRequestSetting requireSession 1
    Require valid-user
</Location>
```

> **DEPRECATION** Previously, HAWKI used `/req/login-shibboleth` as the protected route; however, to maintain
> consistency with other authentication methods, it has been changed to `/req/login`. If you have an existing setup
> using `/req/login-shibboleth`, please update your Apache configuration accordingly.
> For now, to ensure backward compatibility, HAWKI will still accept both routes.

After everything is set up, Apache needs to be restarted.

What Apache does: When the user accesses the `/req/login-shibboleth` route, Apache checks if there is a valid Shibboleth
session. If so, it will inject the Shibboleth attributes into the `$_SERVER` super global and pass the request to HAWKI.
If so, HAWKI will redirect the user to the configured `SHIBBOLETH_LOGIN_URL` with a "target" parameter pointing back to
`/req/login-shibboleth`. After successful login, Shibboleth will redirect the user back to `/req/login-shibboleth`,
which is protected by Shibboleth and Apache will inject the attributes into $_SERVER.
HAWKI will then read the attributes and log the user in.

#### Setting the environment variables

As a next step, we need to configure HAWKI to use Shibboleth as an authentication provider.
Do this by setting the `AUTHENTICATION_METHOD` environment variable in your `.env` file to `Shibboleth`.

Next, we need to configure two urls for Hawki to redirect the user correctly on login:

- `SHIBBOLETH_LOGIN_URL` is the url of your Shibboleth login endpoint, usually something like
  `https://yourdomain.com/Shibboleth.sso/Login`, if the /Shibboleth.sso is available on the same domain as your HAWKI
  instance, feel free to use a relative path like `/Shibboleth.sso/Login`. The "target" parameter will be added
  automatically by HAWKI.
- `SHIBBOLETH_LOGOUT_URL` is the url of your Shibboleth logout endpoint, usually something like
  `https://yourdomain.com/Shibboleth.sso/Logout`, if the /Shibboleth.sso is available on the same domain as your HAWKI
  instance, feel free to use a relative path like `/Shibboleth.sso/Logout`. The "return" parameter will be added
  automatically by HAWKI.

After setting this we need to configure how HAWKI should interpret the attributes sent by Shibboleth.
The following environment variables are available:

- `SHIBBOLETH_USERNAME_VAR` defines the attribute that contains the username, usually something like `eppn` or `uid`.
  The username attribute.
  Defaults to 'REMOTE_USER', which is usually set by the web server to the authenticated user's identifier.
  Other common values are 'uid' or 'eppn' (eduPersonPrincipalName). This value MUST be unique for your installation.
- `SHIBBOLETH_EMAIL_VAR` - The email address for the user for contact and notifications. Defaults to 'mail', which is
  commonly used in Shibboleth installations. Other common values are 'email' or 'mail'. This value MUST be unique for
  your installation.
- `SHIBBOLETH_EMPLOYEETYPE_VAR` - This is a group identifier that is currently primarily used for logging purposes,
  but will, in the future, also be used for permission management. Defaults (historically) to 'employee', but you might
  want to set it to 'affiliation' or 'eduPersonAffiliation' depending on your Shibboleth setup. Common values are '
  affiliation', 'eduPersonAffiliation', 'role' or 'isMemberOf'.
  If you don't have a suitable attribute, you can also set it to a fixed value like 'employee' or 'member'.
- `SHIBBOLETH_NAME_VAR` - The full name of the user for display purposes. Defaults to 'displayName', which is commonly
  used in Shibboleth installations.
  Other common values are 'cn' (common name), 'givenName' (first name) or 'sn' (surname). This value does NOT need to be
  unique.
  If you need to concatenate multiple attributes (e.g. givenName and sn), you can provide a comma-separated list of
  attribute names.
  This way, the attributes will be concatenated with a space in between. If any of them are missing, they will be
  skipped.
  Example: SHIBBOLETH_NAME_VAR="displayName" -> "displayName (John Doe)" => "John Doe"
  Example: SHIBBOLETH_NAME_VAR="givenName,sn" -> "givenName (John) + sn (Doe)" => "John Doe"

A word of advice: Shibboleth is sometimes weird with Apache; leading to Shibboleth values being prefixed with
"REDIRECT_"; HAWKI expects this and will try to find both the normal and the "REDIRECT_" prefixed version of the
attribute. Meaning even if you set SHIBBOLETH_USERNAME_VAR to "uid", the code will search for $_SERVER['uid'] and $_
SERVER['REDIRECT_uid']
