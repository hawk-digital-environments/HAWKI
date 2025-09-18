---
sidebar_position: 8
---

## Overview

HAWKI2 implements a sophisticated and flexible authentication system that supports multiple
authentication methods while maintaining end-to-end encryption. The system integrates with various
enterprise identity providers while ensuring secure key management and user privacy.

---
## Authentication Methods

HAWKI2 supports four authentication methods, configurable via the `AUTHENTICATION_METHOD` environment
variable:

1. LDAP Authentication
2. Shibboleth Authentication
3. OpenID Connect (OIDC)
4. Test Authentication (for development purposes)

---
## Authentication Flow

### Initial Authentication Process

Regardless of the authentication method, the authentication flow follows these steps:

**1. Identity Provider Authentication**
    - User submits credentials to the selected identity provider
    - The identity provider validates credentials and returns user information
    - This verification happens without exposing credentials to HAWKI2's application logic

**2. User Status Determination**
    - System checks if the user exists in the HAWKI2 database
    - For existing users: redirected to /handshake for keychain synchronization
    - For new users: redirected to /register for setting up encryption keys

**3. Session Establishment**
    - Upon successful authentication, Laravel's session-based authentication is established
    - CSRF tokens are regenerated for security
    - Session information is maintained with Laravel Sanctum

### Registration Process (New Users)

For new users, the system follows these additional steps:

**1. User Information Storage**
    - Identity provider information is stored in the session
    - User is guided through the passkey creation process
    - Cryptographic key pair is generated client-side
**2. Key Registration**
    - User's public key is stored in the database
    - Private key and keychain are encrypted client-side
    - Encrypted keychain is backed up on the server
**3. Account Creation**
    - The completeRegistration method finalizes user account setup
    - User is redirected to the application's main interface

### Handshake Process (Returning Users)

For returning users, a secure "handshake" process enables secure access to their encrypted data:

1. Keychain Retrieval
    - The encrypted keychain is retrieved from the server
    - User provides their passkey to decrypt the keychain
    - Client-side decryption prevents exposure of private keys

2. Keychain Synchronization
    - Local and server keychains are compared and synchronized
    - Most recent version is determined through timestamp comparison
    - Updates are applied if necessary

## Authentication Technologies

### LDAP Authentication

Implemented in LdapService.php, this service:

**1. Connects to Enterprise Directory Servers**
    - Uses standard LDAP protocol (PHP's native LDAP functions)
    - Configured with server host, port, and binding credentials
    - Searches for users based on configurable filters

**2. Authenticates Users**
    - Performs two-step LDAP binding (admin bind + user bind)
    - Extracts user attributes from LDAP response
    - Maps LDAP attributes to application user properties using configuration

**3. Configuration**
    - Detailed settings in config/ldap.php
    - Attribute mapping for organizational integration
    - Support for secure LDAP with TLS

### Shibboleth Authentication

Implemented in ShibbolethService.php, this federates authentication:

**1. Service Provider Integration**
    - Works with Shibboleth SP module
    - Reads user attributes from server variables
    - Handles SP-initiated SSO flows

**2. User Provisioning**
    - Creates or updates users based on Shibboleth attributes
    - Generates random passwords for local account security
    - Redirects to the configured Shibboleth login path when needed

**3. Security Features**
    - Session regeneration for protection against session fixation
    - Support for Shibboleth's secure logout process
    - Integration with enterprise identity federations


### OpenID Connect (OIDC)

Implemented in OidcService.php, this provides modern OAuth2-based authentication:

**1. Standards Compliance**
    - Follows OpenID Connect specifications
    - Uses the jumbojett/openid-connect-php library
    - Supports multiple scopes (profile, email)

**2. Token Handling**
    - Manages authentication and ID tokens
    - Retrieves user information from OIDC endpoints
    - Handles token refresh and validation

**3. Provider Integration**
    - Configurable for various OIDC providers
    - Support for test environments with insecure HTTP
    - Extracts standardized claims for user identity

### Test Authentication

Implemented in TestAuthService.php, this provides development convenience:

**1. Simplified Testing**
    - Configuration-based test users
    - No external dependencies for local development
    - Can be activated alongside production methods

### Laravel Sanctum Integration

HAWKI2 uses Laravel Sanctum for API authentication and protection:

**1. Session Authentication**
    - Secure cookies for web-based session management
    - CSRF protection against cross-site request forgery
    - Customizable session timeouts

**2. API Token Authentication**
    - Optional personal access tokens for API access
    - Defined in config/sanctum.php
    - Support for token abilities (scopes)

**3. WebSocket Authentication**
    - Secures WebSocket connections for real-time features
    - Authenticates private channels for secure messaging
    - Prevents unauthorized access to room channels

## Security Considerations

**1. Credential Isolation**
    - Authentication credentials never touch application storage
    - Only identity assertions from trusted providers are used
    - No password storage or handling in the application

**2. Session Security**
    - Session regeneration prevents session fixation attacks
    - Encrypted cookies protect session data
    - Session expiry checking through middleware

**3. Authentication Chain**
    - Multi-step authentication process
    - Independent cryptographic verification layers
    - Separation of authentication and authorization

**4. Route Protection**
    - Middleware stack for route protection:
    - auth: Base authentication check
    - expiry_check: Session timeout verification
    - roomEditor/roomAdmin: Role-based access control
    - registrationAccess: Controls registration flow
    - prevent_back: Prevents back-button security issues

## Configuration and Customization

The authentication system can be configured through:

**1. Environment Variables**
    - AUTHENTICATION_METHOD: Primary auth method
    - Method-specific configurations (LDAP_HOST, OIDC_IDP, etc.)
    - Timeout and security parameters

**2. Config Files**
    - config/auth.php: Core authentication configuration
    - config/sanctum.php: API authentication settings
    - config/ldap.php: LDAP connection details

**3. Service Customization**
    - Each authentication service is encapsulated in its own class
    - Dependency injection allows for service replacement
    - Error handling is centralized in the AuthenticationController

This comprehensive authentication system ensures HAWKI2 can integrate with enterprise identity systems
while maintaining its end-to-end encryption model and the security of user keys and data.