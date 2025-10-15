---
sidebar_position: 4
---

# Authentication providers

HAWKI supports multiple authentication providers to facilitate user login and management.
The available authentication methods include: LDAP, OpenID Connect (OIDC), and SAML (via Shibboleth).
This document tries to help you set up the authentication method that best fits your needs.

## Shibboleth (SAML)

### Configuring Apache:

If Shibboleth is installed with yum or apt-get, the Apache module `mod_shib` will be installed and activated.
What you need to do next is to determine how the actual service should be protected.

You need to tell Apache, which part should be protected by Shibboleth. In our case it is a simple route
`/req/login-shibboleth` that will trigger the Shibboleth login. You can of course, if you want to protect the entire
site, use `/` as the path. Note, that the latter will make the entire site inaccessible without a valid Shibboleth
session and basically disable the `SHIBBOLETH_LOGIN_URL` in your `.env` file.

In your <VirtualHost> you add a <Location> tag for what you want to protect (found in /etc/httpd/conf.d/shib.conf):

```apacheconf
<Location /req/login-shibboleth>
    AuthType shibboleth
    ShibRequestSetting requireSession 1
    Require valid-user
</Location>
```

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
