# Core Concepts

This article covers what every new contributor needs to hold in their head on day one. It deliberately stays at the concept level — class-by-class references are in the sub-articles.

:::tip[Plugin groundwork]
Several patterns described here — `@api`, `DecoratorTrait`, filter events, `DispatchableFilter` — are load-bearing preparations for the HAWKI v3 plugin system. They are not over-engineering. See [Plugin System Preview](../1000-Infrastructure/100-Plugin-System-Preview.md).
:::

## Domain-Driven Design (light)

HAWKI does not implement pure DDD. The "light" variant means:

- Business logic lives in domains under `App\Services\{DomainName}\`.
- Laravel-native classes (Controllers, Models, FormRequests) stay in their conventional `app/` locations.
- Domain events and listeners live inside the domain, not in a global `app/Events/` folder.

The signal to read more: if code feels cross-cutting or unclear about where it belongs, the answer is almost always a domain service, not a utility class.

## Domain directory anatomy

```
app/Services/Ai/
├── Contracts/          ← interfaces for cross-domain communication
├── Events/             ← domain events; may have sub-namespaces for grouping
├── Exceptions/         ← domain-specific exceptions
├── Listeners/          ← event listeners; auto-discovered by bootstrap
├── Repositories/       ← database access only
│   └── Queries/        ← optional focused query objects
├── Values/             ← value objects, DTOs, enums
├── AiFactory.php       ← named collaborator at the domain root
└── AiService.php       ← domain public API (@api)
```

Structural directories (`Contracts/`, `Values/`, `Exceptions/`, `Repositories/`) use plural names. `Utils/` is always a classification failure — every class has a more precise home.

Named collaborators that are direct, single-purpose partners of the domain service live flat at the domain root alongside the service (e.g. `AiFactory` next to `AiService`).

## Naming conventions

- Namespace segments are `CamelCase`, including acronyms: `Ai` not `AI`, `Mcp` not `MCP`, `Http` not `HTTP`.
- `...Service` classes always live at the domain root, never inside a structural namespace.
- Any class named `...Service` is the public API of its domain and must carry `@api`.
- Prefer `Contracts/` over `Interfaces/`.

## Layer responsibilities

| Layer           | Responsibility                                         | Must not                             |
|-----------------|--------------------------------------------------------|--------------------------------------|
| **Controller**  | Receive HTTP, delegate to a service, return a response | Contain business logic, query the DB |
| **FormRequest** | Validate and authorize the request shape               | Access services or repositories      |
| **Service**     | Orchestrate domain workflows                           | Access HTTP, sessions, facades       |
| **Repository**  | Issue DB queries via Eloquent                          | Contain business logic               |
| **Model**       | Declare structure, relationships, casts                | Contain business logic, use facades  |

## Singletons

Two valid mechanisms:

1. `#[Singleton]` attribute directly on the class (preferred for domain services).
2. `$this->app->singleton(...)` in a `ServiceProvider` (needed when the binding requires config at registration time).

Both are equally valid. Do not pick one arbitrarily — use the attribute when the class needs no extra setup, and the ServiceProvider when it does.

## Dependency injection

Inject all dependencies via the constructor. Never use facades or `app()` helpers in services, repositories, or value objects.

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

**Common injections:**

| Need         | How                                                     |
|--------------|---------------------------------------------------------|
| Config value | `#[Config('section.key')] string $value`                |
| Cache        | `#[Cache] Illuminate\Contracts\Cache\Repository $cache` |
| Logging      | `Psr\Log\LoggerInterface $logger`                       |
| Current time | `App\Services\System\Time\CarbonClockInterface $clock`  |

`now()`, `Carbon::now()`, and `new \DateTime()` are banned in services, repositories, and value objects. They make time non-deterministic in tests.

`CarbonClockInterface` extends the PSR-20 `Psr\Clock\ClockInterface` but types `now()` to return `CarbonImmutable` instead of a plain `DateTimeImmutable`, so services get Carbon's API without an extra cast. Both interfaces are bound to the same `CarbonClock` singleton (see `AppServiceProvider::registerClockForInterface()`); inject `Psr\Clock\ClockInterface` instead only when a class needs to stay framework/PSR-agnostic (e.g. shared library code).

## ServiceLocatorTrait (API Resources only)

`ServiceLocatorTrait` (`App\Services\System\Container\ServiceLocatorTrait`) exists because Laravel's JSON:API library instantiates `JsonApiResource` subclasses outside the container's constructor chain. Constructor injection is not available there.

:::warning[Not a general pattern]
`ServiceLocatorTrait` is allowed **only in API Resources**. Never use it in services, models, or repositories. If you feel the urge to use it outside an API Resource, reconsider the design.
:::

In tests, inject mocks explicitly:

```php
$resource->useServiceContainerFallback(false);
$resource->setService(SomeDependency::class, $mock);
```

When running in PHPUnit, the trait automatically disables container fallback so missing mocks throw immediately rather than silently hitting the real container. See [Custom Infrastructure](./250-Custom-Infrastructure.md) for the full explanation.

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

See the [Contributing guide](../../400-Contributing.md) for the full reasoning. The existing `RoomService` uses the bad pattern — it is a known pre-refactor rough edge. See [Technical Debt Register](./300-Technical-Debt.md).

## Events and listeners

Listeners are auto-discovered from `app/Services/*/Listeners` — no manual registration needed. Events live in `app/Services/{Domain}/Events/`.

**Event naming:** past tense (`MessageSentEvent`), present progressive (`CheckingHealthEvent`), or `Before` prefix (`BeforeCreatingRoomEvent`). Always add the `Event` suffix.

**Filter events** (`DispatchableFilter`) are a synchronous hook mechanism that allows listeners to modify data in a pipeline without subclassing. They use `DispatchableFilter` instead of `Dispatchable`, expose controlled getters/setters, and are never queued or broadcast. They are the primary plugin interception point for modifying data in HAWKI's pipelines.

```php
$isAllowed = ModelPermissionFilterEvent::dispatch($user, $model)->isAllowed();
```

## Value objects

Value objects live in `{Domain}/Values/`. They are always `readonly`. Construction goes through static factory methods (`fromXxx`, `tryFromXxx`). No external service dependencies.

## Exceptions

- One dedicated exception class per error condition.
- A domain marker interface (`{Domain}ExceptionInterface extends \Throwable`) that all domain exceptions implement.
- Static factory methods for construction; speaking error messages.

**Who logs?**

| Decision                  | Rule                                                                                     |
|---------------------------|------------------------------------------------------------------------------------------|
| You swallow the exception | You **must** log — no one else will                                                      |
| You re-throw or convert   | Log only the contextual enrichment you add; the next catch site logs its own decision    |
| Unhandled                 | Laravel catches and logs automatically — catch defensively at service boundaries instead |

Never double-log. Pass the full exception object in the PSR log context: `['exception' => $e]`.

## Contextual scopes

`HasContextualScopesTrait` enables per-query scope control without leaking state. Models that use it declare their scopes via `registerScopes(ScopeRegistrar $registrar)`. Callers use `runSandboxed` in a repository to temporarily bypass a scope:

```php
AiModel::scopeContext()->runSandboxed(function (ModelScopeContext $ctx): void {
    $ctx->disableScope('active_filter');
    $allModels = AiModel::all();
});
```

Currently registered on: `AiModel` (`active_filter`, `usage_type_filter`), `AiProvider` (`active_filter`), `AiModelDescription` (`locale_aware`), `AiTool` (`active_filter`), `Room` (`RoomAccessScope`).

See [Custom Infrastructure](./250-Custom-Infrastructure.md#pattern-2-hascontextualscopestrait--abstractrepository--eloquent-scope-control) for the full explanation of why HAWKI uses this instead of plain Eloquent global scopes.

## Model attribute casts

**`AbstractCastableObject`** (`App\Utils\Casts\AbstractCastableObject`) is the base for typed, serializable value objects that are hydrated from and persisted to flat string maps (database rows, config files). Extend it and declare `public` properties — scalar types, enums, dates, encrypted values, and nested castable objects are all supported.

Use `fromStringArray()` to hydrate, `toStringArray()` to serialize.

**Eloquent casts available in HAWKI:**

| Cast                         | Purpose                                                                                           |
|------------------------------|---------------------------------------------------------------------------------------------------|
| `AsInstance`                 | Generic cast for any class implementing `CastableInstanceInterface` (`fromArray()` / `toArray()`) |
| `AsLocale`                   | Casts a DB string to a `Locale` value object via `LocaleService::getMostLikelyLocale()`           |
| `AsAsymmetricPublicKeyCast`  | Transparent asymmetric public key encryption/decryption on a model attribute                      |
| `AsHybridCryptoValueCast`    | Transparent hybrid encryption/decryption                                                          |
| `AsSymmetricCryptoValueCast` | Transparent symmetric AES-GCM encryption/decryption                                               |

## API stability contract

Classes marked `@api` form the stable public surface. `@api` = a promise that the signature will not change without a `@deprecated` cycle first (minimum one minor version notice).

No `@api` = internal, may change at any time.

- Events marked `@api` use `public readonly` properties and past-tense names.
- Eloquent models marked `@api` declare their stable surface via class-level `@property` docblocks and public relationship methods.

See [API Stability](./100-API-Stability.md) for the full contract and all current extension points.
