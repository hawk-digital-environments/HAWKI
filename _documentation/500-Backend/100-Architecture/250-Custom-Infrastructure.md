# Custom Infrastructure

Three places where HAWKI deliberately diverges from Laravel's default approach. New contributors encounter these within the first day of reading code. Without an explanation, each one looks like a bug or over-engineering. This article answers the implied question: "why isn't this done the standard Laravel way?"

---

## Pattern 1: `ServiceLocator` / `ServiceLocatorTrait` — DI in API Resources

### The standard Laravel way

Inject dependencies via the constructor. This works for every class the container instantiates — services, controllers, commands, jobs, listeners, etc.
Anything else goes through `app()` or `Container::getInstance()` to resolve dependencies on demand; neither are easy to mock in tests.

### Why it breaks for some cases?

In some cases Laravel does not provide us a way to inject dependencies via the constructor. A good example are API Resources.
`laravel-json-api/laravel` instantiates `JsonApiResource` subclasses (the schema and resource classes that serialize Eloquent models into JSON:API documents) outside the container's constructor chain. They are created by the framework's serialization machinery, which does not go through `Container::make()`. There is no hook to supply constructor arguments.

### The HAWKI solution

`ServiceLocator` (`App\Services\System\Container\ServiceLocator`) is a lightweight per-class DI container with local overrides and an optional container fallback. `ServiceLocatorTrait` (`App\Services\System\Container\ServiceLocatorTrait`) is the consumer API that API Resources use.

**`ServiceLocatorTrait` API:**

| Method                                                       | Purpose                                                                |
|--------------------------------------------------------------|------------------------------------------------------------------------|
| `getService(string $id): mixed`                              | Resolve a service by class name. Local override wins over container.   |
| `setService(string $id, mixed $service): self`               | Register a service locally. Used in tests to inject mocks.             |
| `useServiceContainerFallback(bool\|null $useFallback): self` | Control whether unresolved services fall back to the global container. |

**PHPUnit auto-detection:**

When `getService()` is called inside PHPUnit and no local service is registered, the trait automatically disables container fallback and throws instead of silently resolving from the real container. This forces test authors to supply controlled values via `setService()`, catching missing mocks immediately.

`useServiceContainerFallback(false)` forces this behaviour explicitly. `true` forces container fallback. `null` resets to auto-detect.

**Test pattern:**

```php
$resource = new UserResource($user);
$resource->useServiceContainerFallback(false);
$resource->setService(AvatarStorageService::class, $mockAvatarStorage);
$array = $resource->toArray($request);
```

:::warning[Use with an eye toward testability]
`ServiceLocatorTrait` and `ServiceLocator` are designed to help with testability. Generally speaking you should always aim for clean constructor injection. Use the service locator only when you have no other option, and always provide a way to override services in tests.
:::

---

## Pattern 2: `HasContextualScopesTrait` + `AbstractRepository` — Eloquent Scope Control

### The standard Laravel way

`Model::addGlobalScope(new MyScope())` applies the scope to every query for that model. Disabling it requires calling `Model::withoutGlobalScope(MyScope::class)` on every query builder where the scope should not apply. Callers must know which scopes exist.

### Why it breaks for HAWKI

HAWKI needs scopes that are *default on, but selectively disableable* for specific queries, without leaking the "disabled" state to the next query. Examples:

- `ActiveFilterScope` hides inactive AI models — but the admin sync command must see all of them.
- `LocaleAwareScope` filters system prompts to the current locale — but a migration job operates locale-independently.
- `UsageTypeFilterScope` restricts models to the requested usage type — but the settings UI needs to see every model.

With plain global scopes, each caller must know the scope name and call `withoutGlobalScope()` manually. Contextual scopes let the model declare which scopes are disableable, and callers use a sandboxed API that automatically restores scope state.

### The HAWKI solution

**`HasContextualScopesTrait`** — applied to an Eloquent model. Requires one method:

```php
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

`ScopeRegistrar::addScope(key, scope, ?disablingGuard)` registers a named scope. The optional `disablingGuard` is a `Closure(): bool` that controls whether a caller is allowed to bypass the scope (e.g. an admin check). The default guard always allows bypass.

**`ScopeContext` singleton** holds the global map of which scopes are disabled for the current request. `ContextualScopeWrapper` wraps the real scope class and checks `ScopeContext` at query time to decide whether to apply.

**`ModelScopeContext`** (obtained via `Model::scopeContext()`) is the entry point for per-model scope control:

- `disableScope(key)` — disables a single scope.
- `runSandboxed(Closure)` — executes the closure, then **automatically restores all scope state** to what it was before, regardless of what the closure changed. This is the primary safe API for bypassing a scope in a repository.

**`AbstractRepository`** (`App\Services\System\Database\Eloquent\Repositories\AbstractRepository`) is the base class for all HAWKI repository classes. It enforces the rule that DB queries live in repositories, not in services. Key API:

| Method                                | Purpose                                     |
|---------------------------------------|---------------------------------------------|
| `findOne(mixed $id): ?Model`          | Find a single record by primary key         |
| `findAll(): Collection`               | Retrieve all records                        |
| `findAllLazy(): LazyCollection`       | Retrieve all records as a lazy collection   |
| `getQuery(): Builder`                 | Fresh query builder with all scopes applied |
| `getQueryWithoutAnyScopes(): Builder` | Query builder bypassing every global scope  |

Repositories are `#[Singleton]` by default via the base class attribute.

**`AbstractRepositoryWithContextualScopes`** extends `AbstractRepository` and adds `runWithScopeDisabled(string $key, Closure $callback)` — a one-liner for the common `scopeContext()->runSandboxed { disableScope(key); ... }` pattern.

**Model guessing:** The repository resolves its associated Eloquent model automatically by stripping the `Repository` suffix and looking up `App\Models\{Name}`. When the repository name does not match the model, add `#[UseModel(MyModel::class)]` to the repository class — this is the escape hatch.

**Currently registered contextual scopes:**

| Model                | Scope key           | What it does                                       |
|----------------------|---------------------|----------------------------------------------------|
| `AiModel`            | `active_filter`     | Hides inactive models                              |
| `AiModel`            | `usage_type_filter` | Restricts to the requested usage type              |
| `AiProvider`         | `active_filter`     | Hides inactive providers                           |
| `AiModelDescription` | `locale_aware`      | Filters to the current locale                      |
| `AiTool`             | `active_filter`     | Hides inactive tools                               |
| `Room`               | `RoomAccessScope`   | Restricts to rooms the current user is a member of |

---

## Pattern 3: `CustomTranslator` + `LaravelTranslationLoaderAdapter` — Translation Override

### The standard Laravel way

`TranslationServiceProvider` registers Laravel's built-in `Translator` and `FileLoader`. Works well for apps that translate server-rendered Blade views only.

### Why it breaks for HAWKI

HAWKI needs three things the built-in translator does not provide:

1. **Merge custom JSON files on top of Laravel's defaults.** HAWKI ships its own `resources/lang/*.json` files that must overlay (not replace) Laravel's own validation messages and other defaults.

2. **Expose labels via JSON:API.** The Svelte frontend fetches translation strings via `GET /api/hawki/v1/translation-labels/{locale}` at startup. Laravel's translator has no mechanism for this.

3. **Locale metadata in the connection payload.** `LocaleService` resolves the current locale from a chain (session → cookie → `Accept-Language` → default) and injects only the locale identifier into the connection bootstrap payload. The built-in `App::getLocale()` does not integrate with this resolution chain.

### The HAWKI solution

`TranslationServiceProvider` replaces Laravel's default provider, wiring `CustomTranslator` as the `translator` binding. This means `trans()` and `__()` still work unchanged everywhere — Blade, controllers, and services all use the same function and hit the same data source.

`LaravelTranslationLoaderAdapter` handles the file-merge layer. `LocaleService` owns the locale-resolution chain. The `TranslationLabels` JSON:API resource exposes labels to the Svelte frontend on demand.

This pattern section explains only **why** the override exists. For how to add translation keys, how the frontend fetches labels, and the locale resolution chain, see [Translation System](../900-Frontend-Integration/050-Translation-System.md).
