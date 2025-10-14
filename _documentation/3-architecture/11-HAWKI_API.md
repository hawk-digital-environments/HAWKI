# HAWKI API Documentation

## Overview

The HAWKI API provides access to AI model capabilities through a secure, token-based authentication system. This documentation outlines how to integrate with HAWKI's API, allowing external applications to leverage HAWKI's AI functionality.

## Authentication

HAWKI uses Laravel Sanctum for API authentication, which provides a lightweight way to authenticate single-page applications, mobile applications, and simple token-based APIs.

### API Tokens

To use the HAWKI API, you need a personal access token:

1. Log in to your HAWKI account
2. Navigate to your Profile
3. In the "API Tokens" section, create a new token with a descriptive name
4. Store the generated token securely - it will only be shown once

**Note**: Token creation via the web interface may be disabled by administrators. In this case, you will need to contact your system administrator to create an API token for you.

### Using Tokens

Include your token in all API requests using the Authorization header:

```
Authorization: Bearer YOUR_TOKEN_HERE
```

### Token Management

#### Web API

HAWKI provides web endpoints for token management:

- **Create Token**: `POST /req/profile/create-token`
  - Body: `{ "name": "Token Name" }`
  - Returns: Token details including the plain text token
  
- **List Tokens**: `GET /req/profile/fetch-tokens`
  - Returns: List of your active tokens
  
- **Revoke Token**: `POST /req/profile/revoke-token`
  - Body: `{ "tokenId": 123 }`
  - Permanently revokes the specified token

#### Command Line Interface

Administrators can manage API tokens for users through the command line:

- **Using Artisan Commands**:
  ```bash
  # Create a token
  php artisan app:token
  
  # Revoke a token
  php artisan app:token --revoke
  ```

- **Using HAWKI CLI**:
  ```bash
  # Create a token
  php hawki token
  
  # Revoke a token
  php hawki token --revoke
  ```

Both methods provide an interactive interface to select a user by username, email address, or user ID, then prompt for token creation or revocation.

## API Endpoints

### User Information

```
GET /api/user
```

Returns information about the authenticated user.

### AI Model Request

```
POST /api/ai-req
```

Send a request to an AI model and receive a response.

#### Request Body

```json
{
  "payload": {
    "model": "model-name",
    "messages": [
      {
        "role": "system",
        "content": {
          "text": "You are a helpful assistant."
        }
      },
      {
        "role": "user",
        "content": {
          "text": "Hello, how are you today?"
        }
      }
    ]
  }
}
```

#### Parameters

- `payload.model` (required): The model ID to use (e.g., "gpt-4o", "meta-llama-3.1-70b-instruct")
- `payload.messages` (required): Array of message objects in the conversation
  - `role` (required): "system", "user", or "assistant"
  - `content.text` (required): The message content

#### Response

```json
{
  "success": true,
  "content": {
    "text": "I'm doing well, thank you for asking! How can I help you today?"
  }
}
```

## Error Handling

The API returns standard HTTP status codes:

- **200 OK**: Request successful
- **401 Unauthorized**: Invalid or missing authentication token
- **403 Forbidden**: External API access is disabled or you lack permissions
- **422 Unprocessable Entity**: Validation errors in the request body
- **500 Internal Server Error**: Server-side error

For validation errors (422), detailed error information is returned:

```json
{
  "success": false,
  "message": "Validation Error",
  "errors": {
    "payload.model": ["The model field is required"]
  }
}
```

## Usage Tracking

All API requests are tracked and count toward your usage limits. Usage records include:

- Model used
- Input and output tokens
- Context (API usage)
- Timestamp

## Configuration

### External API Configuration

Two environment variables control API access in HAWKI:

1. `ALLOW_EXTERNAL_COMMUNICATION`: Controls whether external API requests are allowed at all.
   - `true`: External API requests are permitted
   - `false`: All external API requests are blocked

2. `ALLOW_USER_TOKEN_CREATION`: Controls whether users can create their own API tokens via the web interface.
   - `true`: Users can create, view, and revoke their own API tokens
   - `false`: Only system administrators can create API tokens through command line tools

These settings can be configured in your HAWKI environment configuration (`.env` file):

```
ALLOW_EXTERNAL_COMMUNICATION=true
ALLOW_USER_TOKEN_CREATION=true
```

When `ALLOW_USER_TOKEN_CREATION` is set to `false`, users will see a message indicating that token creation is disabled and they should contact their administrator for API access.

## Available Models

The available models depend on your HAWKI installation's configuration. Common models include:

- OpenAI: gpt-4o, gpt-4o-mini, o1-mini
- GWDG: meta-llama-3.1-8b-instruct, meta-llama-3.1-70b-instruct, mistral-large-instruct
- Google: gemini-1.5-flash, gemini-2.0-flash-lite, gemini-2.5-pro-exp-03-25
- Local models (if configured): Ollama or OpenWebUI models

To see the current list of available models and their capabilities, check your HAWKI configuration or consult with your administrator.

## Rate Limiting

API requests may be subject to rate limiting based on your organization's policies and the configured AI providers' limitations.

## Security Considerations

- Never share your API tokens
- Revoke tokens immediately if compromised
- All API communication should use HTTPS
- Keep your integration code updated with the latest security practices

## Example Usage

### cURL Example

```bash
curl -X POST https://your-hawki-instance.com/api/ai-req \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "payload": {
      "model": "gpt-4o",
      "messages": [
        {
          "role": "user",
          "content": {
            "text": "Summarize the key features of quantum computing."
          }
        }
      ]
    }
  }'
```

### JavaScript Example

```javascript
async function queryHawkiAPI() {
  const response = await fetch('https://your-hawki-instance.com/api/ai-req', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer YOUR_TOKEN_HERE',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      payload: {
        model: 'gpt-4o',
        messages: [
          {
            role: 'user',
            content: {
              text: 'Explain the concept of neural networks in simple terms.'
            }
          }
        ]
      }
    })
  });
  
  const data = await response.json();
  return data;
}
```

## Administration

### Command Line Token Management

HAWKI administrators can manage API tokens through the command line interface, which is particularly useful for:

1. Creating tokens for users in a headless environment
2. Automating token generation for system integration
3. Bulk management of tokens for multiple users
4. Creating tokens when user token creation is disabled

The commands can be run either through Laravel's Artisan or the HAWKI CLI tool:

```bash
# Using Artisan
php artisan app:token
php artisan app:token --revoke

# Using HAWKI CLI
php hawki token
php hawki token --revoke
```

The workflow for creating a token is:
1. Select identification method (Username, Email Address, or UserID)
2. Enter the identification value
3. Specify a token name (16 character maximum)
4. The command will output the token, which should be securely stored

For token revocation, the command will:
1. List all existing tokens for the selected user
2. Prompt for the ID of the token to revoke
3. Confirm revocation status

### User Management

Along with token management, HAWKI provides a command to remove users from the system:

```bash
# Using Artisan
php artisan app:removeuser

# Using HAWKI CLI
php hawki remove-user
```

This interactive command allows administrators to:
1. Select identification method (Username, Email Address, or UserID)
2. Safely remove a user from the system
3. Clean up associated resources

### Security Best Practices

When managing tokens and users via the command line:
- Use secure shell connections when executing commands
- Do not share the token output over insecure channels
- Consider implementing token rotation policies
- Keep logs of token creation and revocation for audit purposes
- Perform user removals during scheduled maintenance periods
- Review all tokens before removing a user to ensure services aren't disrupted

## Support

For API support, please contact your HAWKI administrator or refer to the internal documentation for your organization's specific guidelines and policies regarding API usage.