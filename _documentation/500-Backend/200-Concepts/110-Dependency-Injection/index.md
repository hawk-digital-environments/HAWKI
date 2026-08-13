# Dependency Injection

Inject everything via the constructor. Never use facades or `app()` in services, repositories, or value objects.

## The shape

```php
#[Singleton]
readonly class AiService
{
    public function __construct(
        private AiModelRepository $models,
        #[Config('hawki.aiHandle')]
        private string $aiHandle,
        private Psr\Log\LoggerInterface $logger,
        private App\Services\System\Time\CarbonClockInterface $clock,
    ) {}
}
```

Every dependency is a constructor parameter. The container resolves it. The class is `readonly` so state cannot leak.

## Laravel container attributes

Laravel 13 provides attributes for common injection scenarios. Prefer them over manual `ServiceProvider` binding whenever possible:

```php
public function __construct(
    #[Auth('web')] protected Guard $auth,
    #[Cache('redis')] protected Repository $cache,
    #[Config('app.timezone')] protected string $timezone,
    #[Context('uuid')] protected string $uuid,
    #[DB('mysql')] protected Connection $connection,
    #[Give(DatabaseRepository::class)] protected UserRepository $users,
    #[Log('daily')] protected LoggerInterface $log,
    #[RequestAttribute('organization')] protected Organization $organization,
    #[RouteParameter] protected Photo $photo,
    #[Tag('reports')] protected iterable $reports,
) {}
```

Open the [Laravel container docs](https://laravel.com/docs/13.x/container#bind-attribute) for the full attribute reference.

## Common injections

| Need         | How                                                     |
|--------------|---------------------------------------------------------|
| Config value | `#[Config('section.key')] string $value`                |
| Cache        | `#[Cache] Illuminate\Contracts\Cache\Repository $cache` |
| Logging      | `Psr\Log\LoggerInterface $logger` or `#[Log('daily')]`  |
| Current time | `App\Services\System\Time\CarbonClockInterface $clock`  |
| Current user | `#[CurrentUser] User $user`                              |
| Database     | `#[DB('mysql')] Connection $connection`                  |
| Auth guard   | `#[Auth('web')] Guard $auth`                             |

## Time

`now()`, `Carbon::now()`, and `new \DateTime()` are banned in services, repositories, and value objects. They make time non-deterministic in tests.

`CarbonClockInterface` extends the PSR-20 `Psr\Clock\ClockInterface` but types `now()` to return `CarbonImmutable` instead of a plain `DateTimeImmutable`, so services get Carbon's API without an extra cast. Both interfaces are bound to the same `CarbonClock` singleton; inject `Psr\Clock\ClockInterface` instead only when a class needs to stay framework/PSR-agnostic (e.g. shared library code).

## Singletons

Two valid mechanisms:

1. `#[Singleton]` attribute directly on the class (preferred for domain services).
2. `$this->app->singleton(...)` in a `ServiceProvider` (needed when the binding requires config at registration time).

Use the attribute when the class needs no extra setup, and the ServiceProvider when it does. See the [Laravel container docs](https://laravel.com/docs/13.x/container#bind-attribute) for additional binding attributes like `#[Bind]`, `#[Scoped]`, and `#[Give]`.

## Service decomposition — sub-services, not traits

When a service grows to cover multiple concerns, split it into sub-services via `public readonly` constructor properties. Do not use PHP traits to split a single class across files.

```php
// Good
class RoomService
{
    public function __construct(
        public readonly RoomMemberService $members,
        public readonly RoomMessageService $messages,
        private readonly RoomRepository $repository,
    ) {}
}

// Bad — traits used as a file-splitting mechanism
class RoomService
{
    use RoomFunctions;
    use RoomMembers;   // hidden coupling via $this->method() from another trait
    use RoomMessages;  // invisible dependencies
}
```

Traits hide dependencies, break testability, and make the service boundary invisible. The existing `RoomService` uses the bad pattern — it is tracked in the [Technical Debt Register](../../900-Technical-Debt.md). Do not copy it.

## When constructor injection is not available

Two related tools cover the escape hatches — see [ServiceLocator](./100-ServiceLocator.md) for the full guide:

- **`ServiceLocatorTrait`** — for framework-managed classes where the container does not control instantiation (JSON:API schemas, legacy API resources, Eloquent casts, policies, console commands, scope traits).
- **`ServiceLocator`** (the class) — injectable into services for dynamic, testable callback resolution via `call()`.
