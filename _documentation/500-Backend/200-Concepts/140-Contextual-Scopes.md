# Contextual Scopes

Eloquent global scopes that are **default on, but selectively disableable** for specific queries, without leaking the "disabled" state to the next query. Used when a scope should apply to almost every query but a few callers (admin sync, migration jobs, settings UI) need to see what it hides.

## How a contextual scope is written

A contextual scope is a regular Eloquent `Scope` class. The difference from a plain global scope is how it is registered and controlled — not how it filters.

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ActiveModelScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('is_active', true);
    }
}
```

The scope class can use traits from `app/Models/Scopes/Traits/` to access request context without constructor injection (since Eloquent instantiates scopes itself):

- **`ServiceLocatingScopeTrait`** — gives the scope a `ServiceLocator` instance (see [ServiceLocator](./110-Dependency-Injection/100-ServiceLocator.md)) for resolving dependencies lazily.
- **`UserAwareScopeTrait`** — resolves the current authenticated user via `ServiceLocator`. Provides `getCurrentUser()` and `runIfUserPresent(callback, callbackNoUserInCli)`. Handles the early-routing-stage bypass: before `SystemContextBootingMiddleware` sets the system context, user-aware scoping is disabled because the user is not yet authenticated.
- **`LocaleAwareScopeTrait`** — resolves the current and default locales via `LocaleService`. Provides `getCurrentLocale()` / `getDefaultLocale()` with overridable resolvers.
- **`UsageTypeAwareScopeTrait`** — resolves the current usage type from `UsageContext`.
- **`RequestAwareScopeTrait`** — resolves the current `Request` instance.

Each trait has an `initialize{TraitName}()` method that `ContextualScopeWrapper` calls automatically after resolving the scope instance (see below). The traits compose via `ServiceLocatingScopeTrait`, so a scope that needs both user and locale awareness just uses both traits.

## How scopes plug into Eloquent's scoping logic

`ContextualScopeWrapper` is the bridge between the contextual scope system and Eloquent's global scope pipeline. One wrapper is created per registered scope key and installed as a named Eloquent global scope on the model. At query build time, the wrapper:

1. Checks `ModelScopeContext::isScopeDisabled(key)` — has this scope been disabled for the current context?
2. If disabled, evaluates the **disabling guard** (registered via `ScopeRegistrar::addScope(key, scope, ?disablingGuard)`). When the guard returns `true`, the scope is skipped. When it returns `false`, the **"not-allowed" callback** is invoked.
3. If not disabled (or guard allows it), resolves the inner `Scope` instance lazily (class name → `ServiceLocator::get()`, Closure → `ServiceLocator::call()` with DI, `Scope` instance → used directly), calls trait initializers, and delegates to `Scope::apply()`.

## How to register scopes on a model

```php
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    use HasContextualScopesTrait;

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('active_filter', ActiveModelScope::class);
        $registrar->addScope('usage_type_filter', UsageTypeFilterScope::class);
    }
}
```

`ScopeRegistrar::addScope(key, scope, ?disablingGuard)` registers a named scope. The scope can be a class name (resolved via `ServiceLocator`), a `Scope` instance, or a Closure (invoked via `ServiceLocator::call()` for DI). The optional `disablingGuard` is a `Closure(): bool` that returns `true` when the caller is allowed to bypass the scope; the default guard always allows bypass.

## How to disable a scope for one query — the repository API

When your repository extends `AbstractRepositoryWithContextualScopes`, the sandboxing is built into the protected `getQuery()` method. All standard methods (`findOne`, `findAll`, `findAllLazy`) accept an optional `ScopeOverrides` argument:

```php
// Disable one scope for a single query
$allModels = $this->findAll($this->makeScopeOverrides('active_filter'));

// Disable multiple scopes
$allModels = $this->findAll($this->makeScopeOverrides(['active_filter', 'usage_type_filter']));

// Disable all contextual scopes
$allModels = $this->findAll($this->makeScopeOverrides(true));
```

`makeScopeOverrides(true|array|string|null)` is the convenience factory. Pass a string for one scope, an array for several, `true` for all, `null` for none (the default — all scopes active).

Internally, `getQuery()` wraps the query in `ModelScopeContext::runSandboxed()`, which clones the current scope state, applies the overrides, runs the query, and restores the previous state on return. You never need to call `runSandboxed()` yourself when going through the repository API.

For fine-grained control beyond disabling by key, `getQueryWithScopeContext(Closure)` runs the closure against the `ModelScopeContext` directly — useful when you need to set context values, not just disable scopes.

## How to disable a scope outside a repository

When you need control on the model directly (admin commands, CLI tools):

```php
AiModel::scopeContext()->runSandboxed(function (ModelScopeContext $ctx): void {
    $ctx->disableScope('active_filter');
    $allModels = AiModel::all();
});
```

`runSandboxed()` clones the current scope state, runs the closure, and restores the previous state on return. Whatever the closure changed is gone by the next query.

## How HTTP callers disable scopes

The `no_scope[resource-type]=scopeKey` query parameter disables scopes on JSON:API resources. See [JSON:API Conventions](../300-HTTP-API/100-JSON-API-Conventions.md) for the syntax and the auth requirements.

## "Not-allowed" callbacks — what happens when a guard refuses

When a scope's `disablingGuard` returns `false` (the caller is not permitted to bypass the scope), the system invokes a "not-allowed" callback. The callback decides what happens next. `MakesDisableNotAllowedCallbacksTrait` provides three built-in strategies:

| Factory method | Return value | Effect |
|---|---|---|
| `makeDisableNotAllowedThrowException()` | throws | Aborts with 403. **This is the default** registered on `ScopeContext`. |
| `makeDisableNotAllowedIgnore()` | `false` | Silently keeps the scope active. The query runs with the scope applied. |
| `makeDisableNotAllowedForceDisable()` | `true` | Force-disables the scope despite the guard's refusal. Bypasses security enforcement — use with care. |

The resolution order for which callback applies: globally-disabled callback → locally-disabled callback → scope-specific callback registered via `disableScope(key, $onNotAllowed)` → global default from `ScopeContext`.

Currently the guards are basic (the default always allows bypass), but when the admin panel lands with permission management, `disablingGuard` closures will check user permissions. At that point the "not-allowed" callback strategy becomes critical: a `throw` callback enforces access control, an `ignore` callback degrades gracefully, and a `force-disable` callback overrides the guard for privileged operations.

## Dragons

- **Scope state leaks across queries if you forget `runSandboxed`.** Calling `disableScope()` outside a sandbox disables the scope for the rest of the request. Always use the sandboxed API — the repository API does this for you automatically.
- **The `disablingGuard` closure runs at disable time, not at query time.** A guard that reads `auth()->user()` captures the user at the moment the scope is disabled, not when the query executes.
- **`AiConvAccessScope` is not yet a contextual scope** — it is enforced inside service methods. See [Private Conversations](../400-Domains/200-Chat/230-Private-Conversations.md).

## Source

- `app/Services/System/Database/Eloquent/ContextualScopes/` — trait, registrar, contexts, wrapper
- `app/Services/System/Database/Eloquent/Repositories/AbstractRepositoryWithContextualScopes.php`
- `app/Models/Scopes/Traits/` — reusable scope traits (`UserAwareScopeTrait`, `LocaleAwareScopeTrait`, `UsageTypeAwareScopeTrait`, `RequestAwareScopeTrait`, `ServiceLocatingScopeTrait`)
- `app/Services/System/Database/Eloquent/ContextualScopes/Contexts/MakesDisableNotAllowedCallbacksTrait.php`
