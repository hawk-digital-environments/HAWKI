# MCP 405 Error Fix - CSRF Token Issue

## Problem

RAWKI MCP server returns 405 "Method Not Allowed" error when HAWKI tries to call MCP tools.

## Root Cause

Laravel MCP servers register routes with web middleware (including CSRF protection):

```php
// In Laravel MCP Registrar
Router::post($handle, fn () => $this->bootServer(...))
```

The routes are added via:
```php
Route::prefix('mcp')->group($path);  // Uses web middleware by default
```

HAWKI's `MCPSSEClient` sends POST requests without CSRF tokens, causing Laravel to reject them with a 405 error.

## Solution 1: Exclude MCP Routes from CSRF (Recommended)

### In RAWKI Project

Edit `/Users/arianadmin/Development/rawki/bootstrap/app.php`:

**Before:**
```php
->withMiddleware(function (Middleware $middleware) {
    //
})
```

**After:**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'mcp/*',  // Exclude all MCP routes from CSRF verification
    ]);
})
```

### Why This Works
- MCP is a machine-to-machine protocol (not browser-based)
- CSRF protection is designed for browser requests
- MCP servers should use other authentication methods (API keys, OAuth)

### Restart RAWKI
```bash
cd /Users/arianadmin/Development/rawki
docker-compose restart
```

## Solution 2: Add CSRF Token Support to HAWKI (Not Recommended)

This would require fetching a CSRF token first, which doesn't fit the MCP protocol design.

## Verification

### 1. Check Route Registration
```bash
cd /Users/arianadmin/Development/rawki
php artisan route:list | grep mcp
```

Expected output:
```
POST   mcp/rawki  mcp-server.rawki
```

### 2. Test the Endpoint
```bash
curl -X POST http://localhost:8080/mcp/rawki \
  -H "Content-Type: application/json" \
  -H "Accept: text/event-stream" \
  -d '{"jsonrpc":"2.0","id":"test","method":"tools/list","params":{}}'
```

Should return SSE stream, not 405 error.

### 3. Test from HAWKI
Ask the AI:
```
"Search for information about Laravel 11"
```

Check logs:
```bash
tail -f storage/logs/laravel.log | grep "RAWKI"
```

## Additional Considerations

### Security
Since you're removing CSRF protection from MCP routes, consider:

1. **Use API Authentication** - Add API key validation in RAWKI
2. **Restrict by IP** - Only allow localhost/docker network
3. **Use OAuth** - Laravel MCP supports OAuth flows

### Example: Add API Key Middleware to RAWKI

Create middleware in RAWKI:
```php
// app/Http/Middleware/ValidateMcpApiKey.php
public function handle($request, Closure $next)
{
    $apiKey = $request->header('X-MCP-API-Key');

    if ($apiKey !== config('mcp.api_key')) {
        abort(401, 'Invalid API key');
    }

    return $next($request);
}
```

Register in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['mcp/*']);

    $middleware->group('mcp', [
        \App\Http\Middleware\ValidateMcpApiKey::class,
    ]);
})
```

## Files Affected

### RAWKI
- `bootstrap/app.php` - CSRF exception

### HAWKI
- No changes needed if using Solution 1

## Understanding Laravel 11 Middleware

Laravel 11 uses a new middleware configuration in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    // Configure middleware here
})
```

Available methods:
- `validateCsrfTokens(except: [...])` - Exclude routes from CSRF
- `group('name', [middleware])` - Define middleware groups
- `web([middleware])` - Add to web middleware
- `api([middleware])` - Add to API middleware

## Why Not Use API Routes?

You might wonder why MCP routes aren't registered as API routes (which don't have CSRF):

```php
// This would avoid CSRF issues
Route::prefix('mcp')->middleware('api')->group($path);
```

The Laravel MCP package registers routes via the service provider with `Route::prefix()` which defaults to web middleware. You'd need to modify the package to change this, which is not recommended.

## Alternative: Use API Middleware for MCP

If you want to avoid modifying CSRF settings, you could register MCP routes with API middleware in RAWKI:

**In `routes/api.php`:**
```php
use App\Mcp\Servers\RawkiServer;
use Laravel\Mcp\Server\Facades\Mcp;

Mcp::web('rawki', RawkiServer::class);
```

**Update `bootstrap/app.php`:**
```php
->withRouting(
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'mcp',  // Change API prefix from /api to /mcp
    web: __DIR__.'/../routes/web.php',
    ...
)
```

But this conflicts with the MCP service provider's automatic route loading, so Solution 1 is cleaner.

## Status

After applying Solution 1 (CSRF exception), the MCP integration should work correctly.

Expected behavior:
1. HAWKI sends POST to `http://localhost:8080/mcp/rawki`
2. RAWKI processes request (no CSRF check)
3. RAWKI returns SSE stream with tool results
4. HAWKI parses results and sends to AI model
