---
name: hawki-backend
description: HAWKI coding standards and architecture patterns. Use when writing or reviewing PHP code for HAWKI, creating new classes/services, or when asked about project structure, DDD patterns, or code conventions.
---

> **Note:** The current codebase may not fully follow these guidelines — folder names are inconsistent and some naming conventions are mid-migration. Follow these rules in all new code and refactor old code toward them when possible.

## Architecture: Lightweight DDD

Business logic lives in `App\Services\{Domain}\`. Laravel-native classes (Controllers, Models, FormRequests, Events, Listeners) stay in conventional `app/` locations but mirror domain structure via subfolders.

**Naming rules:**

- Domain namespaces: singular noun (`Auth`, `Storage`, `Announcement`)
- Structural namespaces: plural for countable nouns (`Exceptions`, `Values`, `Contracts`, `Repositories`), singular for mass nouns (`Middleware`)
- All namespace segments are `CamelCase`, including acronyms — `Ai` not `AI`, `Mcp` not `MCP`, `Http` not `HTTP`
- Prefer `Contracts/` over `Interfaces/`
- `Utils/` is always a classification failure — every class has a more precise home

```
app/Services/Ai/
├── Clients/         # Group of ClientInterface decorators
├── Contracts/       # Interfaces for cross-domain communication
├── Events/          # Domain events (always at domain root)
├── Listeners/       # Domain listeners (auto-discovered from Services/*/Listeners)
├── Repositories/    # Database access (Repository + optional Queries/)
│   └── Queries/     # Complex/reused query objects
├── Exceptions/      # Domain exceptions
├── Providers/       # External provider integrations
├── Values/          # Value objects, DTOs, enums
├── AiFactory.php    # Named collaborator (direct partner of AiService)
└── AiService.php    # Domain service (@api)

app/Http/Controllers/Ai/
app/Http/Requests/Ai/
app/Http/Resources/Ai/
app/Models/Ai/
```

## Layer Rules

**Controllers** — HTTP only. Delegate validation to FormRequest, call one service method, return ApiResource. No business logic, no DB access.

**FormRequests** — All validation and authorization logic lives here. Never validate in controllers.

**API Resources** — Transform models into JSON responses. Live in `App\Http\Resources\{Domain}\`. Laravel instantiates these outside the container, so constructor injection is unavailable — use `ServiceLocatorTrait` when a service is needed.

```php
class MessageResource extends JsonResource {
    use ServiceLocatorTrait;
    public function toArray(Request $request): array {
        $formatter = $this->getServiceInstance(MessageFormatterService::class);
        return ['id' => $this->id, 'content' => $formatter->format($this->content)];
    }
}
```

**Services** (`@api`) — All business logic. Constructor-injected deps only. No HTTP/session/request dependencies. Always stateless, registered as `#[Singleton]`, lightweight at construction.

- `...Service` classes always live at the domain root, never inside a structural namespace
- When a service grows, split into sub-services exposed as `public readonly` constructor properties — never use traits to split a service file

```php
class RoomService {
    public function __construct(
        public readonly RoomMemberService $members,
        public readonly RoomMessageService $messages,
        private readonly RoomRepository $repository,
    ) {}
    public function create(array $data): Room { ... }
}
// Callers: $roomService->members->add($slug, $data);
```

- When sub-services need parent behaviour, inject the parent — don't use trait inheritance
- A sub-service that other services inject directly should be a standalone service instead

**Naming collaborators correctly** — Internal strategy implementations are not "Services". Example: `LdapAuthProvider`, `OidcAuthProvider` implement `AuthProviderInterface` and live in `Auth/Providers/Ldap/` etc. Only `AuthService` is `@api`.

**Aggregating services** — expose sub-services via `public readonly` properties matching the structural namespace (`files` → `Storage/Files/`, `avatars` → `Storage/Avatar/`).

**Repositories** (suffix `Repository`) — Thin injectable Eloquent wrappers. Never call model statics from services or controllers.

```php
readonly class AiModelRepository {
    public function findActiveByProvider(string $providerId): Collection {
        return AiModelConfig::where('provider_id', $providerId)->where('active', true)->get();
    }
}
```

For complex or reused queries, extract a `Query` object into `Repositories/Queries/`. Each owns exactly one SQL query. Simple one-off lookups: inline Eloquent in the repository is fine.

```php
// Repositories/Queries/FindActiveModelsByProviderQuery.php
readonly class FindActiveModelsByProviderQuery {
    public function execute(string $providerId): Collection {
        return AiModelConfig::where('provider_id', $providerId)->where('active', true)->get();
    }
}
```

Eloquent query scopes belong in Repository classes or Query objects, not in models.

**Models** — Data descriptors only: relationships, casts, accessors. No business logic, no query scopes, no facades, no `ServiceLocatorTrait`, no static/global state.

**Value Objects** — `readonly`, in `{Domain}/Values/`. Static factory methods (`from...`, `tryFrom...`). No external deps.

```php
readonly class StoredFileIdentifier {
    private function __construct(
        public string $uuid,
        public StoredFileCategory $category,
        public string $extension,
    ) {}

    public static function fromCategoryAndUuid(StoredFileCategory $category, string $uuid, string $extension): self {
        return new self($uuid, $category, $extension);
    }
}
```

**Enums** — For any constrained set of values. Live in `{Domain}/Values/`.

**Exceptions** — In `{Domain}/Exceptions/`. Each domain has a marker interface `{Domain}ExceptionInterface extends \Throwable`. Static factory methods only; speaking error messages via `sprintf`.

```php
interface FileConverterExceptionInterface extends \Throwable {}

class ConversionFailedException extends \RuntimeException implements FileConverterExceptionInterface {
    public static function forUnsupportedMimeType(string $mimeType, string $converterClass): self {
        return new self(sprintf('Converter "%s" cannot handle MIME type "%s".', $converterClass, $mimeType));
    }
}
```

Rules:

- Never throw built-in PHP exceptions directly — create a dedicated class with static factory methods
- Catch `\Throwable`, not `\Exception`
- Log only at the catch site making a decision; never double-log
- PSR log context: `['exception' => $e]` (reserved key — triggers stack trace formatting)

**API Resources** — see above.

**Events** — live in `App\Services\{Domain}\Events\`, not `app/Events/` (legacy). **Listeners** — action names (`NotifyRoomMembers`). Live in `App\Services\{Domain}\Listeners\`; auto-discovered.

Event naming — pick one tense to express when the event occurs:

- Past tense (completed): `MessageSentEvent`, `RoomCreatedEvent`
- Present progressive (ongoing): `CheckingHealthEvent`, `AiWritingStartedEvent`
- "Before" prefix (about to happen): `BeforeCreatingRoomEvent`

Always add the `Event` suffix. Event classes are `readonly` by default (best practice — pure data carriers). Non-`readonly` is valid when required by framework serialization, but is usually a design smell. Always include `declare(strict_types=1)` and the `Dispatchable` trait. Constructor args must be strongly typed `public readonly` properties — never raw `array` payloads.

```php
readonly class RoomCreatedEvent
{
    use Dispatchable;
    public function __construct(public Room $room) {}
}
```

**Filter events** — Mutable synchronous hooks that let listeners influence core execution logic. Named with the `...FilterEvent` suffix. Never `readonly`, never `ShouldBroadcast` or `ShouldQueue`. Use `App\Events\Traits\DispatchableFilter` (not `Dispatchable`) — it omits `broadcast()` and returns the event instance from `dispatch()`. Properties are private; read-only context gets a getter only, mutable results get a getter + setter:

```php
class ModelPermissionFilterEvent {
    use DispatchableFilter;
    private bool $allowed;
    public function __construct(
        private readonly User $user,      // read-only context — getter only
        private readonly AiModel $model,  // read-only context — getter only
        bool $allowed = true,
    ) { $this->allowed = $allowed; }
    public function getUser(): User { return $this->user; }
    public function getModel(): AiModel { return $this->model; }
    public function isAllowed(): bool { return $this->allowed; }
    public function setAllowed(bool $allowed): void { $this->allowed = $allowed; }
}

// Dispatch and read back mutated state in one line:
$isAllowed = ModelPermissionFilterEvent::dispatch($user, $model)->isAllowed();
```

Broadcasting events (`ShouldBroadcast`): define `broadcastOn()` (prefer `PrivateChannel`) and `broadcastWith(): array` explicitly. Add `SerializesModels` when the event carries Eloquent models **and** may hit a queued listener — it serializes the model to its PK and rehydrates it on dispatch. Eager-loaded relations are not preserved; use `withoutRelations()` or pass a value object instead if that matters. Add `InteractsWithSockets` only when you need `dontBroadcastToCurrentUser()` / `toOthers()` to exclude the triggering socket.

**Contracts (Interfaces)** — Only when multiple/replaceable implementations exist. No speculative interfaces.

## API Stability & Decoration

- `@api` = stable public surface; class never `final`; no signature changes until next major version
- Applies to interfaces too — interfaces without `@api` are internal to the domain only
- `@deprecated` required before removal, with target version and migration path
- Class `@api` without method tags = entire public+protected surface is stable

```php
// Decorate an @api service via ServiceProvider
class DecoratedAiService extends AiService {
    use DecoratorTrait;
    public function getModels(): AiModelCollection {
        return $this->filter(parent::getModels());
    }
}
$this->app->extend(AiService::class, fn($orig) => DecoratedAiService::createDecoratedOf($orig));
```

## Code Standards

Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) for formatting.

**Every PHP file:**

```php
<?php
declare(strict_types=1);
```

**PHP native classes** — always fully qualified with leading backslash, never imported via `use`:

```php
// Good
} catch (\Throwable $e) {}
class MyException extends \RuntimeException {}
// Bad
use \Throwable;
} catch (Throwable $e) {}
```

**Types** — always declare param and return types; avoid `mixed`; DocBlock only for complex arrays or generic collections:

```php
/** @return Collection<int, AiModelConfig> */
public function getActiveModels(): Collection { ... }
```

**Dependency Injection** — constructor injection always. No facades or `app()` in services, Repository classes, or value objects.

```php
#[Singleton]
readonly class AiService {
    public function __construct(
        private AiModelRepository $repository,
        #[Config('hawki.aiHandle')] private string $aiHandle,
        private Psr\Log\LoggerInterface $logger,
    ) {}
}
```

| Need         | How                                                                                        |
|--------------|--------------------------------------------------------------------------------------------|
| Config value | `#[Config('app.key')] string $value`                                                       |
| Cache        | `#[Cache] Illuminate\Contracts\Cache\Repository $cache`                                    |
| Logging      | `Psr\Log\LoggerInterface $logger`                                                          |
| Filesystem   | `Illuminate\Contracts\Filesystem\Filesystem $fs`                                           |
| Singleton    | `#[Singleton]` attribute on the class                                                      |
| DB access    | Create a `Repository` class — never inject the `DB` facade                                 |
| Current time | `Psr\Clock\ClockInterface $clock = new Clock()` (default: `Symfony\Component\Clock\Clock`) |

**ServiceLocatorTrait** — anti-pattern. Only allowed in API Resources (instantiated outside container). Never in models.

```php
class UserResource extends JsonResource {
    use ServiceLocatorTrait;
    public function toArray(Request $request): array {
        $service = $this->getServiceInstance(AvatarStorageService::class);
    }
}
```

**Configuration** — Never `env()` in application code. Always through config files; inject via `#[Config]`.

**Date & Time** — Never use `now()`, `new \DateTime()`, `new \DateTimeImmutable()`, or `Carbon::now()` in services, Repository classes, or value objects. Always inject `Psr\Clock\ClockInterface` as a constructor dependency:

```php
readonly class MyService {
    public function __construct(
        private Psr\Clock\ClockInterface $clock = new Symfony\Component\Clock\Clock(),
    ) {}
    public function doSomething(): void {
        $now = $this->clock->now(); // \DateTimeImmutable
    }
}
```

Exception: config and migration files (no DI container).

**DocBlocks** — Only when types are insufficient (complex shapes, generics, non-obvious intent). Use `//` inside function bodies, never `/** */`.

**No:** `dd()`, `dump()`, `var_dump()`, hardcoded values, facades in services.

## Testing (PHPUnit)

**Test namespaces:**

- **Unit tests:** `Tests\Unit\{mirrored namespace}` — mirrors `app/` structure
- **Feature tests:** `Tests\Feature\{relevant sub-namespace}`

**Test methods:**

- Start with `testIt...` (void return), e.g., `testItConstructs`, `testItCanRetrieveXy`
- Class under test var: `$sut`
- Constructor tests: `testItConstructs` only (verify instantiation)
- Assertions: `static::assertSame()` not `$this->assertSame()`
- Exception tests: assert message too; mirror `sprintf` syntax in test

**Coverage:** Use `#[CoversClass]`, `#[CoversTrait]`, `#[CoversMethod]` from `PHPUnit\Framework\Attributes`. Never tag interfaces.

**Structure:** Arrange-Act-Assert — setup, execute, verify.

**Data providers:**

```php
public static function provideTestItDoesSomethingData(): iterable {
    yield 'label' => ['value1', 'value2'];
}
```

Name: `provideTestItDoesSomethingData` (return `iterable` with `yield`).

**Fixtures:** Separate files, one per fixture. Sub-namespace next to test: `MyClassXyTest\MyClassXyTestFixtures\`.

**Mocking:** External dependencies only (repositories, HTTP, APIs). Not value objects or utilities. Use `setUp`/`tearDown` for shared setup.

**Avoid:** Reflection on private methods (test public API), over-mocking (proves nothing), hardcoded paths (use `sys_get_temp_dir()`/`tempnam()`), large test methods (split > 20 lines).

**PHPStan:** Fix type errors; suppress only genuine false positives in third-party code.
