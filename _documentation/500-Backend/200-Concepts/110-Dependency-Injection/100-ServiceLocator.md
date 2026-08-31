# ServiceLocator

Two related but distinct tools for resolving dependencies when constructor injection is not available or when you need dynamic, testable callback resolution. See [Dependency Injection](./index.md) for the primary pattern — this page covers the escape hatches.

## `ServiceLocatorTrait` — for framework-managed classes

`ServiceLocatorTrait` (`App\Services\System\Container\ServiceLocatorTrait`) is used in classes that Laravel or third-party libraries instantiate outside the container's constructor chain. The trait gives each instance a per-class DI container with local overrides.

### Where it is used

- **JSON:API schemas and resources** (`laravel-json-api/laravel` instantiates these outside the container)
- **Legacy API Resources** (`app/Http/Resources/Legacy/`)
- **Eloquent casts** (`AsLocale` — the cast is resolved by Eloquent, not the container)
- **Policies** (`ExtAppPolicy`)
- **Console commands** that need testable resolution (`GenerateRepositoryHelperCodeCommand`)
- **Scopes** (via `ServiceLocatingScopeTrait`, which wraps `ServiceLocator`)

### The API

```php
use App\Services\System\Container\ServiceLocatorTrait;

class UserResource extends JsonResource
{
    use ServiceLocatorTrait;

    public function toArray(Request $request): array
    {
        $avatarStorage = $this->getService(AvatarStorageService::class);
        // ...
    }
}
```

- `getService(string $id): mixed` — resolve a service by class name. Local override wins over the container.
- `setService(string $id, mixed $service): self` — register a service locally. Used in tests to inject mocks.
- `useServiceContainerFallback(bool|null $useFallback): self` — control whether unresolved services fall back to the global container.

### How to test

```php
$resource = new UserResource($user);
$resource->useServiceContainerFallback(false);
$resource->setService(AvatarStorageService::class, $mockAvatarStorage);
$array = $resource->toArray($request);
```

`useServiceContainerFallback(false)` forces the resource to throw when a service is not registered locally instead of silently hitting the real container. When running inside PHPUnit, the trait automatically disables container fallback so that forgetting to inject a mock throws immediately. `useServiceContainerFallback(true)` forces fallback; `null` resets to auto-detect.

## `ServiceLocator` — the class

`ServiceLocator` (`App\Services\System\Container\ServiceLocator`) is the backing implementation behind the trait, but it is also designed to be **injected into services** for dynamic and extensible patterns. It is not a patch on a broken system — it is a deliberate tool for cases where you need:

1. **Service resolution with local overrides** — `set()` / `get()` with local-first, container-fallback semantics.
2. **Callback execution with injectable dependencies** — `call()` resolves a callable's parameters via the container (using Laravel's `Container::call()` logic), but can be overridden per-execution-ID via `setCallParams()` for tests.

The `call()` mechanism is what makes `ServiceLocator` interesting as an injectable service. It lets you hook into Laravel's "resolve dependencies from a callable" logic while being fully testable and mockable — every callback invocation can be intercepted by registering pre-set parameters under a dot-path execution ID.

```php
// In a scope wrapper (real usage from ContextualScopeWrapper):
$scope = $this->serviceLocator->call(
    ['scopeWrapper.evaluate.disabling.guard', $this->scopeKey],
    $guard
);

// In a test:
$serviceLocator->setCallParams('scopeWrapper.evaluate.disabling.guard.active_filter', [false]);
```

### Injecting it

`ServiceLocator` is a singleton bound in the container. Inject it when a service needs the `call()` mechanism:

```php
class MyService
{
    public function __construct(
        private ServiceLocator $serviceLocator,
    ) {}

    public function doWork(): void
    {
        $result = $this->serviceLocator->call('myService.doWork', function (SomeDependency $dep) {
            return $dep->execute();
        });
    }
}
```

In tests, `setCallParams('myService.doWork', [$mockDep])` bypasses the container entirely — the callback runs with the mock, no real dependency is resolved.

## Dragons

- **`ProfileService` and `PasskeyService` violate the trait usage rule** — they use `ServiceLocatorTrait` in services where constructor injection was available. See [Technical Debt](../../900-Technical-Debt.md).
- **The trait's PHPUnit auto-detection is intentional.** Forgetting a mock in a test should be a loud failure, not a silent fallback to the real container.

## Source

- `app/Services/System/Container/ServiceLocatorTrait.php`
- `app/Services/System/Container/ServiceLocator.php`
