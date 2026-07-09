# HAWKI API Documentation

> **This document describes legacy API functionality.** The primary API surface since v2 is the JSON:API v1 server at `/api/hawki/v1`. The `POST /api/ai-req` endpoint described below is a deprecated legacy endpoint maintained for backwards compatibility only — do not use it in new integrations. External application integration now uses the OAuth-like ext-app flow documented in [Backend → External Apps](../500-Backend/800-Encryption-and-Security/200-External-Apps.md).
>
> Personal access tokens (`app:token` command, `ALLOW_USER_TOKEN_CREATION`) still exist as a distinct mechanism and the documentation for those remains valid.

## Authentication

HAWKI uses Laravel Sanctum for API authentication.

### Personal Access Tokens

To use the HAWKI API with a personal access token:

1. Log in to your HAWKI account
2. Navigate to your Profile
3. In the "API Tokens" section, create a new token with a descriptive name
4. Store the generated token securely — it will only be shown once

**Note**: Token creation via the web interface may be disabled by administrators (`ALLOW_USER_TOKEN_CREATION=false`). In that case, an administrator must create the token via the CLI.

### Using Tokens

Include your token in all API requests using the Authorization header:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

### Token Management via CLI

For CLI-based token creation and revocation see [Backend → Artisan Commands](../500-Backend/1000-Infrastructure/200-Artisan-Commands.md) (Personal Access Token Commands section).

## Configuration

Two environment variables control personal API access:

- `ALLOW_EXTERNAL_COMMUNICATION` — `true` permits external API requests; `false` blocks all of them.
- `ALLOW_USER_TOKEN_CREATION` — `true` lets users create tokens via the web UI; `false` restricts token creation to administrators using the CLI.

```
ALLOW_EXTERNAL_COMMUNICATION=true
ALLOW_USER_TOKEN_CREATION=true
```

## Legacy Endpoint — `POST /api/ai-req`

> **Deprecated.** Do not use in new integrations. See [Backend → JSON API](../500-Backend/300-JSON-API.md) for the current API.

This endpoint accepted a `payload.model` + `payload.messages` array and returned a synchronous AI response. It is retained for backwards compatibility only and may be removed in a future release.

## Support

For API support, please contact your HAWKI administrator or refer to the internal documentation for your organization's specific guidelines and policies regarding API usage.
