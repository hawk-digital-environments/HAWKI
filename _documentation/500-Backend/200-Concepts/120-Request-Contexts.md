# Request Contexts

Two request-scoped singletons carry **who** is calling and **what surface** the call comes from. Middleware populates both before any controller sees the request. Every service that needs the caller injects them via constructor.

## UserContext — WHO

`App\Services\System\UserTypes\UserContext` answers who is making the current request. It holds a `WellKnownUserTypes` token:

- `GUEST` — unauthenticated visitor (default)
- `REGISTERING_USER` — mid-registration; partial user data via `getRegisteringUser()`
- `USER` — fully authenticated HAWKI user; the `User` model via `getAuthenticatedUser()`
- `EXTERNAL_APP` — external app credential, before a real user is resolved

`getUser()` returns whichever identity shape currently applies (`RegisteringUser|User|null`) without the caller having to branch on the type first. `getAuthenticatedUser()` resolves the `User` model through the injected `Illuminate\Contracts\Auth\Factory` guard.

Any call to `UserContext::set()` that changes the type immediately dispatches `UserTypeChangedEvent` so listeners can react synchronously (for example, middleware that locks the keychain after logout).

## UsageContext — WHAT surface

`App\Services\System\UsageTypes\UsageContext` answers which surface the request comes from:

- `MAIN_APP` — standard HAWKI browser interface (default)
- `EXTERNAL_APP` — external application integration

Contextual scopes and AI dispatch both read `UsageContext` to apply the right filtering. For example, only AI models enabled for the external-app usage type are exposed to external callers.

## Why these exist

The rest of the system trusts these singletons because they are written exactly once, early in the middleware stack, and are read-only thereafter (`UsageContext` fully; `UserContext`'s type token is set once, while `getAuthenticatedUser()`/`getUser()` resolve live through the guard on every call). No service needs to call `Auth::user()` directly — it injects `UserContext` via the constructor and calls `getAuthenticatedUser()` or `getUser()` instead.

## Dragons

Both singletons are request-scoped. Never resolve them in a test without explicit setup. They are populated by `SystemContextBootingMiddleware`; if your test does not run that middleware, you must populate the contexts manually before exercising the service under test.
