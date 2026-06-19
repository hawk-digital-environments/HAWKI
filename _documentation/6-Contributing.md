# Contributing to HAWKI

Thank you for contributing to HAWKI. This guide covers everything you need: workflow, architecture, and code standards. All participants are expected to treat others with respect and courtesy.

---

## Table of Contents

1. [How to Contribute](#how-to-contribute)
2. [Development Workflow](#development-workflow)
3. [Architecture & Code Organization](#architecture--code-organization)
4. [Code Standards](#code-standards)
5. [Testing](#testing)
6. [Frontend Code](#frontend-code)
7. [Styling](#styling)
8. [Pull Request Process](#pull-request-process)
9. [Code Review](#code-review)
10. [AI Agents](#ai-agents)
11. [Getting Help](#getting-help)

---

## How to Contribute

- **Bug reports:** Search existing issues first. Include reproduction steps, environment details, and error messages.
- **Feature suggestions:** Check existing issues. Describe the use case and why it benefits users.
- **Bug fixes:** Reference the issue in your commit.
- **New features:** Discuss in an issue before implementing. Keep scope focused.
- **Documentation:** Fix typos, clarify explanations, keep docs in sync with code.

---

## Development Workflow

### Branching Strategy

| Branch             | Purpose                                                                                                         |
|--------------------|-----------------------------------------------------------------------------------------------------------------|
| **`development`**  | **Default branch** — bleeding edge. All feature and bugfix PRs target here.                                     |
| **`main`**         | Stable release. Only updated by the release pipeline, never directly.                                           |
| **`feature/*`**    | New functionality (e.g., `feature/user-notifications`)                                                          |
| **`bugfix/*`**     | Issue fixes (e.g., `bugfix/login-validation`)                                                                   |
| **`hotfix/*`**     | Urgent fixes branched from `main` and merged back into both `main` and `development`                            |
| **`hawk/testing`** | Deployment branch for the HAWK testing environment — pushing here triggers an automated Docker build and deploy |
| **`hawk/prod`**    | Deployment branch for the HAWK production environment — same pipeline, production infrastructure                |

`development` is the default branch because it represents the current state of active work. `main` reflects what has been released. Contributors always branch from `development` and open PRs against `development`. The release process, versioning strategy, and pipeline details are described in [`_changelog/README.md`](https://github.com/hawk-digital-environments/HAWKI/blob/development/_changelog/README.md).

```bash
git checkout development && git pull upstream development
git checkout -b feature/your-feature-name
```

### Commit Messages

We follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).

```
type(scope): short summary of what changed

Optional body explaining what and why (not how). Wrap at 72 characters.

Refs #123
```

**Types:** `feat` · `fix` · `docs` · `style` · `refactor` · `test` · `chore`

**Rules:** Subject line under 50 characters · reference related issues.

### Keeping Your Branch Updated

```bash
git fetch upstream
git rebase upstream/development
git push origin feature/your-feature-name --force-with-lease
```

---

## Architecture & Code Organization

### Domain-Driven Design (Light)

HAWKI follows a **lightweight Domain-Driven Design** approach. Code is organized around business concepts (domains) rather than technical layers, making the codebase easier to reason about as it grows.

> **Note:** Laravel is not designed for DDD out of the box. We use a pragmatic "light" variant: business logic is organized into domains under `App\Services`, while Laravel-native classes (Controllers, Models, FormRequests, API Resources) remain in their conventional `app/` locations. Events and Listeners are domain concerns and live inside the domain under `App\Services\{Domain}\Events\` and `App\Services\{Domain}\Listeners\`. This avoids fighting the framework while still gaining the organizational benefits of domain thinking.
>
> For more background on Domain-Driven Design, see [martinfowler.com/bliki/DomainDrivenDesign](https://martinfowler.com/bliki/DomainDrivenDesign.html).

### Domain Structure

Domains live under `App\Services\{DomainName}\`. Each domain owns its business logic, database access, and domain types.

#### Naming & Pluralization

- **Domain namespaces** use the natural singular noun of the domain concept (`Auth`, `Storage`, `Announcement`)
- **Structural namespaces** use the plural for countable nouns (`Exceptions`, `Values`, `Contracts`, `Traits`), and singular for mass/uncountable nouns (`Middleware`)
- **All namespace segments are `CamelCase`**, including acronyms — `Ai` not `AI`, `Mcp` not `MCP`, `Http` not `HTTP`. This keeps PSR-4 autoloading consistent and avoids ambiguity when acronyms appear mid-path (e.g. `Ai\Tools\Mcp` reads unambiguously; `AI\Tools\MCP` does not).
- Prefer `Contracts/` over `Interfaces/` or `Interface/`

```
app/Services/
└── Ai/                         ← Domain (singular noun, CamelCase)
    ├── Clients/                 ← Structural namespace (group of ClientInterface decorators)
    │   ├── LoggingClient.php
    │   └── ToolCallingClient.php
    ├── Contracts/              ← Interfaces for cross-domain communication
    │   └── ModelProviderInterface.php
    ├── Events/                 ← Domain events (always at the domain root)
    │   ├── ModelEvents/        ← optional sub-namespace for grouping
    │   │   └── ModelStatusChanged.php
    │   └── AiRequestCompleted.php
    ├── Repositories/           ← Database access (repositories + optional query objects)
    │   ├── Queries/
    │   │   └── FindActiveModelsByProviderQuery.php
    │   └── AiModelRepository.php
    ├── Exceptions/             ← Domain-specific exceptions
    │   └── ModelNotFoundException.php
    ├── Providers/              ← External provider integrations (structural namespace)
    ├── Values/                 ← Value objects, DTOs, enums
    │   ├── AiModelCollection.php
    │   └── UsageType.php  ← Enum (Type suffix)
    ├── AiFactory.php           ← Named collaborator, direct partner of AiService
    └── AiService.php           ← Domain service (@api)
```

Laravel-native classes mirror this domain structure through subfolders:

```
app/
├── Http/
│   ├── Controllers/Ai/
│   ├── Requests/Ai/
│   └── Resources/Ai/
└── Models/Ai/
```

#### Domain Services & Public API

Any class named `...Service` is the **public API** of its domain and must carry the `@api` docblock marker. `...Service` classes **always live in the domain root** — never inside a structural namespace.

Domains should aim for **one focused service entry point** to avoid feature creep. Multiple `...Service` classes at the domain root are allowed where sensible, but must be a deliberate decision.

Services must always be:

- **Stateless** — no mutable instance variables
- **Singletons** — registered with `#[Singleton]`
- **Lightweight at construction** — no heavy initialization in the constructor; defer expensive work to factories or the first method call that needs it

#### Domain Root Cleanliness

The domain root is not exclusively for services. Named collaborators that are direct, single-purpose partners of the domain service also live flat here (e.g. `AiFactory`, `AvailableModelsBuilder`). The signal to move a class into a structural namespace is fit, not complexity:

- **Fits a structural archetype** (`Exceptions/`, `Values/`, `Contracts/`, `Repositories/`) → put it in that directory
- **Multiple classes collaborate with each other** around a shared concept → extract them into a named structural namespace (e.g. `AI/Client/` for a group of `ClientInterface` decorators)
- **`Utils/` is always a classification failure** — every class has a more precise home. If you feel the urge to create one, classify more precisely.

#### Structural Namespaces

A structural namespace is an **organizational tool**, not a domain boundary. It follows the same naming rules as a domain directory. The key constraint: **`...Service` classes inside a structural namespace must bubble up to the domain root**. Value objects, entities, and contracts inside a structural namespace stay where they are — they are accessed through the service.

When internal complexity grows, apply this decision sequence:

1. **Can it be its own domain?** Always preferred — extract it.
2. **Can the logic be merged into the main domain service?** Do that.
3. **Neither?** Create a structural namespace. If a `...Service` inside it cannot be merged into the main domain service, that is a signal it should have been its own domain — go back to step 1.

When a pattern starts to emerge, refactor into a clean structural namespace. If the refactoring would break `@api` compatibility, add a `@todo` for the next major release instead.

#### Naming Collaborators Correctly — Providers as an Example

Not every class that behaves like a "service" should be called one. A concrete example: LDAP, OIDC, and Shibboleth are auth *strategy implementations*, not standalone services. They cannot be injected directly by consumers — they are internal implementations of `AuthProviderInterface`, orchestrated by `AuthService`. Naming them `LdapService` etc. misrepresents their role.

The correct structure names them as providers and groups them in a dedicated structural namespace:

```
Auth/
├── Providers/                       ← structural namespace, internal
│   ├── Ldap/
│   │   ├── Exceptions/
│   │   ├── Values/
│   │   ├── LdapAttributeReader.php
│   │   └── LdapAuthProvider.php     ← no @api, internal only
│   ├── Oidc/
│   │   └── OidcAuthProvider.php
│   └── Shibboleth/
│       └── ShibbolethAuthProvider.php
├── Contracts/
│   └── AuthProviderInterface.php
├── Exceptions/
├── Values/
│   └── AuthenticatedUserInfo.php
└── AuthService.php                  ← @api, the only public entry point
```

#### Aggregating Services

When a domain has multiple sub-services, expose them through a single aggregating service as `public readonly` constructor-injected properties. This keeps a single injection point while mirroring the namespace structure in the public API:

```php
class StorageService
{
    public function __construct(
        public readonly FileStorageService $files,
        public readonly AvatarStorageService $avatars,
    ) {}
}

// Callers use a single injection point:
$storage->files->retrieve($identifier);
$storage->avatars->store($file);
```

Property names should mirror the structural namespace they represent (`files` → `Storage/Files/`, `avatars` → `Storage/Avatar/`).

### Layer Responsibilities

#### Controllers

Handle HTTP requests only. No business logic.

- Delegate validation to `FormRequest` classes
- Call one service method
- Return an `ApiResource` (JSON) or redirect
- No direct database access, no conditional logic beyond routing

```php
// ✅ Good — thin controller
public function store(CreateMessageRequest $request, MessageService $messageService): MessageResource
{
    return new MessageResource($messageService->createMessage($request->validated()));
}
```

#### FormRequests

All validation and authorization logic lives here. Never validate in controllers.

> **TODO:** Detailed guidance on the distinction between validation, authorization (`authorize()`), and Laravel Policies/Gates is deferred — it will be elaborated alongside the plugin architecture documentation.

#### API Resources

Transform models into JSON responses. Live in `App\Http\Resources\{Domain}\`.

Resources are the serialization boundary: they decide which fields and relationships to expose and how to format values. Since Laravel instantiates Resources outside the container, constructor injection is not available. When a Resource needs a service, use `ServiceLocatorTrait` (see [Dependency Injection](#dependency-injection)).

```php
class MessageResource extends JsonResource
{
    use ServiceLocatorTrait;

    public function toArray(Request $request): array
    {
        $formatter = $this->getService(MessageFormatterService::class);
        return [
            'id'      => $this->id,
            'content' => $formatter->format($this->content),
        ];
    }
}
```

#### Services

Contain all business logic and domain workflows.

- Single responsibility per service
- No dependency on HTTP, sessions, or request objects
- All dependencies injected via constructor
- Reusable across controllers, jobs, and commands

##### Service Decomposition — Sub-Services

When a service grows to cover multiple distinct concerns, split it into **sub-services** rather than using traits to split the file.

**Why not traits?** Using traits purely to break a large service into smaller files is a code-organisation hack, not an architectural solution. It produces hidden cross-trait coupling (`$this->method()` that lives in a different file), invisible injected dependencies (a trait silently relying on a constructor parameter it does not declare), and a flat method surface with no grouping signal. Traits exist for *horizontal reuse across unrelated classes* — not for splitting a single class.

**The correct pattern:** Extract each distinct concern into its own service class and expose it through the parent service as a `public readonly` property. The parent service remains the `@api` surface of the domain; the sub-services are internal implementation detail.

```php
// ✅ Good — explicit sub-domain services

class RoomService
{
    public function __construct(
        public readonly RoomMemberService $members,
        public readonly RoomMessageService $messages,
        private readonly RoomRepository $repository,
    ) {}

    // Core room operations live directly on this class
    public function create(array $data): Room { ... }
    public function load(string $slug): Room { ... }
    public function delete(string $slug): bool { ... }
}

// Callers use the grouped API:
$roomService->members->add($slug, $data);
$roomService->messages->sendMessage($data, $slug, $user);
```

```php
// ❌ Bad — traits used as a file-splitting mechanism

class RoomService
{
    use RoomFunctions;   // core operations
    use RoomMembers;     // calls $this->delete() from RoomFunctions — hidden coupling
    use RoomMessages;    // uses $this->messageHandler — invisible dependency
}
```

**When does a sub-service become a standalone service?** If a concern is broad enough that controllers or other services need to inject it directly — independently of the parent — it should be a standalone service, not a sub-service property. The `public readonly` accessor pattern is appropriate when callers always go through the parent service and the sub-service has no meaningful identity on its own.

**Cross-service dependencies in sub-services:** A sub-service sometimes needs to trigger behaviour owned by the parent (e.g., `RoomMemberService` deletes the room when the last member leaves). Inject the parent service into the sub-service — do not call `$this->parentMethod()` through trait inheritance. If this creates a circular dependency, extract the shared logic into a third collaborator or use a domain event.

#### Repositories

All database access is encapsulated in dedicated `Repository` classes (suffix: `Repository`). **Never call Eloquent model statics directly from services or controllers.**

**Why not call models directly?** Models cannot be injected into the container and therefore cannot be mocked. A `Repository` class is injectable and mockable, making test setup explicit and reliable.

```php
// ✅ Good — injectable and mockable
readonly class AiModelRepository
{
    public function findActiveByProvider(string $providerId): Collection
    {
        return AiModelConfig::where('provider_id', $providerId)
            ->where('active', true)
            ->get();
    }
}

// Usage in a service
class AiService
{
    public function __construct(private readonly AiModelRepository $repository) {}

    public function getModels(string $providerId): Collection
    {
        return $this->repository->findActiveByProvider($providerId);
    }
}
```

**Query objects (optional, encouraged for complex or reused queries):** When a query is complex or appears in more than one place, extract it into a dedicated `Query` object inside a `Queries/` structural namespace within the domain's `Repositories/` directory. The repository injects and composes these objects, keeping itself a clean API surface rather than a growing god-class:

```
AI/
├── Repositories/
│   ├── Queries/
│   │   └── FindActiveModelsByProviderQuery.php
│   └── AiModelRepository.php
```

```php
// ✅ A focused query object — injectable, testable, reusable
readonly class FindActiveModelsByProviderQuery
{
    public function execute(string $providerId): Collection
    {
        return AiModelConfig::where('provider_id', $providerId)
            ->where('active', true)
            ->get();
    }
}

// ✅ Repository composes queries — stays thin
readonly class AiModelRepository
{
    public function __construct(
        private readonly FindActiveModelsByProviderQuery $findActiveModels,
    ) {}

    public function findActiveByProvider(string $providerId): Collection
    {
        return $this->findActiveModels->execute($providerId);
    }
}
```

Each `Query` object owns exactly one SQL query. For simple, one-off lookups, inline Eloquent calls in the repository are perfectly fine — do not over-engineer.

Eloquent query scopes are a convenience mechanism — their logic belongs in `Repository` classes or `Query` objects, not in models.

#### Models

Models are **data descriptors only**. They define structure, relationships, and casts. They do not perform work.

**Allowed:**

- Eloquent relationships (`hasMany`, `belongsTo`, etc.)
- Attribute casts and accessors
- Simple helper methods that operate only on data already present on the instance

**Forbidden:**

- Business logic or workflows
- Direct database queries, query scopes, or global scopes (belongs in `Repository` classes or `Query` objects)
- Cache access, external service calls, or facade usage
- Static or global state
- `ServiceLocatorTrait`

The reason: Models cannot use constructor injection, so any external dependency leads back to facades — defeating the DI principle. Keep models stupid.

#### Value Objects & DTOs

Value objects represent domain concepts as typed, immutable structures. They live in `{Domain}/Values/`.

- Always `readonly` (unless there is a specific reason not to be)
- Use static factory methods (`from...`, `tryFrom...`) for construction; keep constructors simple or `private`
- May include helper methods that derive additional data from their own properties
- No external dependencies — no services, no database access

```php
readonly class StoredFileIdentifier
{
    private function __construct(
        public string $uuid,
        public StoredFileCategory $category,
        public string $extension,
    ) {}

    public static function fromCategoryAndUuid(
        StoredFileCategory $category,
        string $uuid,
        string $extension,
    ): self {
        return new self($uuid, $category, $extension);
    }
}
```

**When can a value object do more?** When the convenience gain clearly outweighs the cost of the object knowing too much. A concrete example: a file value object that holds a filesystem reference for lazy content loading saves the entire call stack from having to pass a filesystem dependency around — the efficiency gain is real and the coupling is contained. The test to apply: *is this domain logic tightly tied to this value's own data, or am I sneaking a service in through the back door?* If you are unsure, discuss with the team before proceeding. The default remains: value objects hold data, they do not do work.

#### Enums

Use enums instead of plain strings for any constrained set of values. Enums live in `{Domain}/Values/` right beside your other value objects.

```
Values/
├── UsageType.php      ← enum
├── OnlineStatus.php   ← enum
└── StoredFileCategory.php  ← enum
```

#### Exceptions

Exceptions live in `{Domain}/Exceptions/`. Every domain defines one marker interface — `{Domain}ExceptionInterface` — that extends `\Throwable`. All exceptions in the domain implement it. Sub-domains may define additional narrower interfaces. This allows callers to catch an entire domain's exceptions with a single type.

**Design rules:**

- Never throw built-in PHP exceptions directly. Always create a dedicated exception class that extends the appropriate base (`\InvalidArgumentException`, `\RuntimeException`, etc.).
- Every exception class exposes one or more **static factory methods** that receive the contextual data and build a complete, actionable error message. Do not construct exceptions with `new` from outside the class.
- Error messages must be **speaking and helpful**: describe what the caller tried to do, what went wrong, and — when possible — how to fix it. Use `sprintf` to compose messages.
- The constructor may be `private` when only the static factories should be used, but this is not required.
- When the same exception is appropriate in multiple distinct situations with slight variations in the message, add a dedicated factory method for each.
- When catching exceptions without targeting a specific type, always catch `\Throwable` — not `\Exception`. `\Throwable` covers both `\Exception` and `\Error`.

```php
// App\Services\FileConverter\Exception\FileConverterExceptionInterface.php
interface FileConverterExceptionInterface extends \Throwable {}

// App\Services\FileConverter\Exception\ConversionFailedException.php
class ConversionFailedException extends \RuntimeException implements FileConverterExceptionInterface
{
    public static function forUnsupportedMimeType(string $mimeType, string $converterClass): self
    {
        return new self(sprintf(
            'Converter "%s" cannot handle MIME type "%s". '
            . 'Register a converter that supports this type, or check the file before passing it.',
            $converterClass,
            $mimeType,
        ));
    }

    public static function forExternalToolFailure(string $tool, string $reason): self
    {
        return new self(sprintf(
            'External tool "%s" failed during conversion: %s. '
            . 'Ensure the tool is installed and accessible on the server.',
            $tool,
            $reason,
        ));
    }
}
```

**Who logs?**

Exceptions are dumb — they carry data and a message, they never log themselves. Logging is the responsibility of the **catch site that makes a decision**:

- **If you swallow** (return `null`, `false`, or continue iteration): you **must** log here, because no one else will. Include the operation context, not just `$e->getMessage()`.
- **If you re-throw or convert** to a domain exception: log only the contextual enrichment you are adding. The next catch boundary up the chain will log its own decision. Do not log the same failure twice.
- **Unhandled exceptions**: Laravel will catch and log these automatically, but that means the application crashes. Catch defensively at service boundaries so callers receive a clean failure signal.
- **Log the exception** Use the PSR log's context parameter to include the full exception object (`['exception' => $e]`) — this ensures you get the stack trace and all details in your logs, not just the message. **IMPORTANT** `exception` is a reserved key in PSR logging — using it triggers special handling in most loggers that formats the exception nicely. Do not log exceptions as plain messages or with custom keys.

```php
// ✅ Swallow + log — caller gets null, no duplicate logging
try {
    return $this->fileStorage->retrieve($identifier);
} catch (\Throwable $e) {
    $this->logger->error('Failed to retrieve file for attachment ' . $identifier->uuid, ['exception' => $e]);
    return null;
}

// ✅ Convert + minimal log — caller receives a clean domain exception
try {
    $this->connect($config);
} catch (\Throwable $e) {
    $this->logger->error('LDAP connection failed: ' . $e->getMessage());
    throw AuthFailedException::forConnectionFailure($host, $e);
}

// ❌ Double log — the outer catch will log again
$this->logger->error('...', ['exception' => $e]);
throw $e; // and the next handler also logs $e
```

#### Events & Listeners

**Listeners** are named as **actions** (`NotifyRoomMembers`, `LogMessageActivity`) and live in `App\Services\{Domain}\Listeners\`, co-located with their domain. The application bootstrap auto-discovers listeners from `app/Services/*/Listeners`, so no manual registration is needed for domain listeners.

**Events** live in `App\Services\{Domain}\Events\`, not in the global `app/Events/` folder (legacy, do not add to it). The `Events/` directory always sits at the domain root; events may be grouped into sub-namespaces for organizational purposes.

```
app/Services/
└── Room/
    ├── Events/                    ← always at the domain root
    │   ├── Members/               ← optional sub-namespace for grouping
    │   │   ├── MemberJoinedEvent.php
    │   │   └── MemberLeftEvent.php
    │   └── MessageSentEvent.php   ← ungrouped event, directly inside Events/
    └── Listeners/
        └── NotifyRoomMembers.php  ← listener, co-located with the domain
```

##### Naming events

Event names must clearly express **when** the event occurs relative to the action, using one of three tenses:

- **Past tense** — something completed: `MessageSentEvent`, `RoomCreatedEvent`
- **Present progressive ("while")** — something ongoing: `CheckingHealthEvent`, `AiWritingStartedEvent`
- **"Before" prefix** — something about #to happen: `BeforeCreatingRoomEvent`, `BeforeSendingMessageEvent`

Always add the `Event` suffix to clearly identify the class as an event.

##### Writing event classes

Declare `readonly` on event classes. This is **best practice** — events are pure data carriers and immutability makes them easier to reason about. Deviation is sometimes valid (e.g., when integrating with framework serialization mechanisms), but if you feel the need to mutate an event, it is usually a sign of a design issue — see the mutable state section below.

Always include `declare(strict_types=1)` and the `Dispatchable` trait. Constructor arguments must be **strongly typed** `public readonly` properties — never use untyped properties or raw `array` payloads. Use a typed value object instead.

```php
<?php
declare(strict_types=1);

namespace App\Services\Room\Events;

use App\Models\Room;
use Illuminate\Foundation\Events\Dispatchable;

readonly class RoomCreatedEvent
{
    use Dispatchable;

    public function __construct(
        public Room $room,
    ) {}
}
```

##### Filter events

A **filter event** is a special, explicitly mutable event that acts as a synchronous hook, allowing listeners to influence the execution logic of a core feature. Filter events are named with the `...FilterEvent` suffix (e.g., `ModelPermissionFilterEvent`, `BeforeSendingMessageFilterEvent`).

**Rules:**

- **Never** implement `ShouldBroadcast` or `ShouldQueue` — filter events are **always synchronous**.
- Use `App\Events\Traits\DispatchableFilter` instead of `Illuminate\Foundation\Events\Dispatchable`. It omits `broadcast()` (banned for filter events) and returns the event instance from `dispatch()` so mutated state can be read immediately.
- Properties are **private**, not `public readonly`. The event exposes a controlled API through **getters and setters**.
- **Not all properties need to be writable.** Read-only context (e.g., the originating model or user) is exposed through a getter only. Only properties that listeners are expected to modify get a setter.
- Do **not** declare the class `readonly` — it is intentionally mutable.

```php
<?php
declare(strict_types=1);

namespace App\Services\Ai\Events;

use App\Events\Traits\DispatchableFilter;
use App\Models\Ai\AiModel;
use App\Models\User;

class ModelPermissionFilterEvent
{
    use DispatchableFilter;

    private bool $allowed;

    public function __construct(
        private readonly User $user,       // read-only context
        private readonly AiModel $model,   // read-only context
        bool $allowed = true,
    ) {
        $this->allowed = $allowed;
    }

    // Read-only context — getter only
    public function getUser(): User { return $this->user; }
    public function getModel(): AiModel { return $this->model; }

    // Mutable result — getter + setter
    public function isAllowed(): bool { return $this->allowed; }
    public function setAllowed(bool $allowed): void { $this->allowed = $allowed; }
}
```

A listener that denies access simply calls `$event->setAllowed(false)` and returns. Because `DispatchableFilter::dispatch()` returns the event instance, the calling service reads the final state immediately:

```php
$isAllowed = ModelPermissionFilterEvent::dispatch($user, $model)->isAllowed();
```

##### Broadcasting events (`ShouldBroadcast`)

- Implement `ShouldBroadcast`.
- Define `broadcastOn()` returning a `Channel` or `PrivateChannel`. Prefer `PrivateChannel` for user/room-scoped data.
- Define `broadcastWith(): array` to control the payload explicitly — do not rely on default property serialization.

**`SerializesModels`** — add this trait when the event carries Eloquent model instances **and** may be handled by queued listeners. It transparently serializes the model to its primary key and rehydrates it when the listener runs. Two caveats:

- Eager-loaded relations are **not preserved** — they are reloaded from the database on deserialization, which may bypass your original query constraints.
- If you only need a subset of data, consider passing a value object or scalar instead of the full model to avoid unexpected queries (use `withoutRelations()` on the model when passing it if relations are not needed).

**`InteractsWithSockets`** — only add this trait when you need to **exclude the triggering user's own socket** from receiving the broadcast. It unlocks `dontBroadcastToCurrentUser()` / `toOthers()` / `broadcastToEveryone()`. If you don't need that control, omit it.

> **TODO:** Detailed guidance on when to use events versus direct service calls, sync vs. async dispatch, and the role of events in the plugin architecture is deferred — it will be elaborated alongside the plugin system documentation.

#### Contracts (Interfaces)

Use interfaces where you expect **multiple or replaceable implementations** of the same concept. A concrete example is `FileConverterInterface`, which multiple converter handlers implement — the interface enforces the contract and allows the system to swap implementations without changing callers.

Do not introduce interfaces speculatively. If there is only one implementation and no plan to replace it, a plain class is fine.

#### API Stability (`@api`)

Classes and methods marked with `@api` in their DocBlock form the **stable public surface** of a domain. This rule also includes interfaces; those without the marker are only for the domain itself! `@api` is a promise:

- The signature will not change until the next major version, even as features are added
- Removal requires a `@deprecated` tag first, with a clear description of **when** it will be removed and **how to migrate**

```php
/**
 * @api
 */
class AiService
{
    /**
     * @api
     * @deprecated Will be removed in v3.0. Use getAvailableModels() instead.
     */
    public function getModels(): AiModelCollection { ... }
}
```

Everything **without** `@api` is internal and may change at any time — do not depend on it from outside its domain.

> **Note:** If a class has the `@api` tag but none of its methods do, the entire public and protected surface of the class is considered stable. This is the common pattern for service classes.

**What exactly is stable?** The public and protected signatures of `@api`-marked classes and methods. Private implementation details — private fields, private methods, internal state — may still change between versions. Decorators (see below) that only call `public` and `protected` methods are safe; decorators that rely on private internals are not.

**Versioning and `@deprecated`:** The versioning strategy that determines when something is scheduled for removal is described in [`_changelog/README.md`](https://github.com/hawk-digital-environments/HAWKI/blob/development/_changelog/README.md). The `@deprecated` tag must always reference a target version and a migration path.

#### Extension & Decoration

`@api` classes and their methods are **never `final`** — neither the class declaration nor individual methods. This is intentional: HAWKI is designed with a plugin system in mind, and `@api` classes must remain open for decoration and extension.

To decorate an `@api` service, use Laravel's `$this->app->extend()` together with `App\Utils\DecoratorTrait`. The trait uses reflection to copy all properties from the original instance into the decorator without calling the constructor, preserving runtime state while allowing individual methods to be overridden. See the class docblock in [`App\Utils\DecoratorTrait`](https://github.com/hawk-digital-environments/HAWKI/blob/development/app/Utils/DecoratorTrait.php) for full details, limitations, and caveats.

```php
// DecoratedAiService.php
class DecoratedAiService extends AiService
{
    use DecoratorTrait;

    public function getModels(): AiModelCollection
    {
        // Override specific behaviour, the rest delegates to the parent
        $models = parent::getModels();
        return $this->filter($models);
    }
}

// In a ServiceProvider
$this->app->extend(AiService::class, function (AiService $original) {
    return DecoratedAiService::createDecoratedOf($original);
});
```

Internal (non-`@api`) classes may be `final` and carry no such guarantee.

#### Singletons

Register stateless or internally-cached services as singletons in a ServiceProvider, or use the `#[Singleton]` attribute directly on the class (see [Dependency Injection](#dependency-injection)).

```php
// Via ServiceProvider
$this->app->singleton(StorageService::class, fn ($app) =>
    new StorageService(config('storage.driver'))
);
```

---

## Code Standards

### Code Style

HAWKI follows the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style guide for all PHP code. JavaScript and other frontend assets are formatted with [Prettier](https://prettier.io/).

To keep formatting consistent, the project ships with CLI tooling that runs the formatters for you:

```bash
# Format all PHP files using php-cs-fixer
bin/env style php
# If not running in a docker container
composer run php-cs-fixer

# Format all JS/frontend files using Prettier
bin/env style js
# If not running in a docker container
npm run prettier
```

Run the appropriate formatter before every commit. The "You executed the code formatters" item in the [Code Quality Checklist](#code-quality-checklist) refers to this step.

> **Heads-up — automated enforcement is coming:** Style is currently a manually enforced convention. Starting with **HAWKI 3.0.0**, all pull requests will be validated automatically by the CI pipeline and will fail if formatting issues are detected. Run the formatters locally now to avoid surprises later.

### Strict Types

Every PHP file must declare `strict_types=1`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Ai;
```

### PHP Native Classes — Fully Qualified Names

PHP built-in classes (`\Throwable`, `\RuntimeException`, `\InvalidArgumentException`, `\DateTime`, etc.) must always be written with a **leading backslash** and never imported via `use`. This makes it immediately clear which classes are native PHP versus user-defined or vendor code. Many packages define classes with identical names, and IDE auto-import can silently pull in the wrong one at runtime.

```php
// ✅ Good
} catch (\Throwable $e) { ... }
class MyException extends \RuntimeException {}

// ❌ Bad
use \Throwable;
} catch (Throwable $e) { ... }
```

### Type Declarations

Always declare parameter and return types. Use the most specific type possible; avoid `mixed`.

```php
// ✅ Full types
public function findModel(string $modelId): ?AiModelConfig { ... }

// ✅ Union (PHP 8.0+)
public function getId(): int|string { ... }

// ❌ No types
public function calculateTotal($quantity, $price) { ... }
```

For complex array shapes or generic collections, add a DocBlock:

```php
/** @return Collection<int, AiModelConfig> */
public function getActiveModels(): Collection { ... }
```

### Dependency Injection

Always inject dependencies via the constructor. Never use facades or `app()` helpers in services, `Repository` classes, or value objects.

#### Laravel Attributes (Preferred for Framework Services)

Laravel's [contextual attributes](https://laravel.com/docs/13.x/container#contextual-attributes) make injecting framework services clean without requiring knowledge of which concrete class a facade resolves to:

```php
#[Singleton]
readonly class AiService
{
    public function __construct(
        private AiModelDb $db,
        #[Config('hawki.aiHandle')]
        private string $aiHandle,
    ) {}
}
```

#### Common Injections

| Need              | How to inject                                              |
|-------------------|------------------------------------------------------------|
| Config value      | `#[Config('app.key')] string $value`                       |
| Cache             | `#[Cache] Illuminate\Contracts\Cache\Repository $cache`    |
| Logging           | `Psr\Log\LoggerInterface $logger`                          |
| Filesystem        | `Illuminate\Contracts\Filesystem\Filesystem $fs`           |
| Mail              | `Illuminate\Contracts\Mail\Mailer $mailer`                 |
| Singleton service | `#[Singleton]` attribute on the class                      |
| DB access         | Create a `Repository` class — never inject the `DB` facade |

#### ServiceLocatorTrait (Allowed Exception)

> **⚠ This is an anti-pattern. Use it only when constructor injection is genuinely impossible — currently that means API Resources. If you feel the urge to use this anywhere else, you are almost certainly solving the wrong problem. Stop and reconsider the design.**

In classes that Laravel instantiates outside the container (e.g., API Resources), constructor injection is not available. In these cases, `App\Utils\ServiceLocatorTrait` provides a testable alternative to facades. See the class docblock in [`App\Utils\ServiceLocatorTrait`](https://github.com/hawk-digital-environments/HAWKI/blob/development/app/Utils/ServiceLocatorTrait.php) for the full rationale and API.

```php
class UserResource extends JsonResource
{
    use ServiceLocatorTrait;

    public function toArray(Request $request): array
    {
        $service = $this->getService(AvatarStorageService::class);
        // ...
    }
}

// In tests — inject mocks explicitly. setFailOnMissingLocalService(true) causes
// the trait to throw if any service is resolved from the real container,
// catching accidental test leakage early.
$resource = new UserResource($user);
$resource->setFailOnMissingLocalService(true);
$resource->setService(AvatarStorageService::class, $mockService);
```

**Never use `ServiceLocatorTrait` in models.** Models are data descriptors and must have no service dependencies at all.

### Configuration

Never access environment variables directly in application code. Go through config files.

```php
// ❌
$key = env('API_KEY');

// ✅ In config/api.php
return ['key' => env('API_KEY')];

// ✅ Injected via attribute in a class
#[Config('api.key')] private string $apiKey
```

`env()` returns `null` when the config cache is active (`php artisan config:cache`), making direct usage unreliable in production.

### Date & Time

Never use `now()`, `new \DateTime()`, `new \DateTimeImmutable()`, `Carbon::now()`, or any other direct time construction in services, `Db` classes, or value objects. Always inject `Psr\Clock\ClockInterface` as a constructor dependency and use it to obtain the current time.

This makes time deterministic in tests — no mocking of globals or facades required.

```php
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;

readonly class MyService
{
    public function __construct(
        private ClockInterface $clock = new Clock(),
    ) {}

    public function doSomething(): void
    {
        $now = $this->clock->now(); // \DateTimeImmutable
    }
}
```

The `Symfony\Component\Clock\Clock` is the default implementation and resolves to the system clock at runtime. In tests, pass a `\Symfony\Component\Clock\MockClock` or any other `ClockInterface` implementation to control time precisely.

The only exception is config files and migration files, where there is no DI container and time is genuinely a constant of the deployment context.

### Documentation

PHP 8 type declarations make most DocBlocks redundant. Write DocBlocks only when:

- The method has complex array shapes (`@param array{...}`)
- The intent or side effects are non-obvious from the code
- Generic collection types need annotation (`@return Collection<int, User>`)

Inside function bodies use `//` or `/* */` — never `/** */`.

```php
// ✅ No DocBlock needed — types and name are clear
public function verify(User $user): bool { ... }

// ✅ DocBlock needed — explains non-obvious business rule
/**
 * Only processes refunds for orders older than 30 days.
 * Newer orders are handled by the instant refund service.
 */
public function processDelayedRefund(Order $order): void { ... }

// ❌ Avoid — duplicates what the signature already says
/** @param User $user @return bool */
public function verify(User $user): bool { ... }
```

### Code Quality Checklist

Before submitting your PR:

- [ ] `declare(strict_types=1)` in every PHP file
- [ ] All parameters and return types declared
- [ ] Dependencies injected via constructor or `#[Config]` / `#[Cache]` attributes
- [ ] No facades in services, `Repository` classes, or value objects
- [ ] `ServiceLocatorTrait` used only where constructor injection is impossible (not in models)
- [ ] No `env()` calls outside config files
- [ ] All database access goes through a `Repository` class — no static model calls in services
- [ ] Models contain no business logic, no facades, no query scopes, no service locator
- [ ] Value objects are `readonly` with `from...` / `tryFrom...` factory methods
- [ ] Enums used for all constrained string or int values
- [ ] DocBlocks only where needed (complex types, non-obvious intent)
- [ ] No `now()`, `new \DateTime()`, `Carbon::now()`, or similar — use injected `Psr\Clock\ClockInterface`
- [ ] No debug statements (`dd()`, `dump()`, `var_dump()`)
- [ ] No hardcoded values (use config or constants)
- [ ] You provided good test coverage for new features and bug fixes
- [ ] You executed the code formatters

> **Tip:** [AI tools](#ai-agents) can help enforce these standards. Check our [contributing skills guide](pathname://attachments/skills/contributing/SKILL.md) for automated assistance.

---

## Testing

### Backend / PHP

HAWKI uses [PHPUnit](https://phpunit.de/) for both unit and feature (integration) tests.

#### Running Tests

| What                      | In Docker                  | Locally (Composer)              |
|---------------------------|----------------------------|---------------------------------|
| Unit tests only           | `bin/env test php unit`    | `composer run test:unit`        |
| Feature tests only        | `bin/env test php feature` | `composer run test:feature`     |
| Static analysis (PHPStan) | `bin/env test php stan`    | `composer run test:stan`        |
| All of the above          | `bin/env test php all`     | *(run each command separately)* |

> **AI Tools:** Our [testing skills guide](pathname://attachments/skills/phpunit/SKILL.md) provides guidance on writing effective unit and feature tests using PHPUnit, including best practices for assertions, data providers, mocking, and test structure.

#### PHPUnit — Unit & Feature Tests

**Core Principles**

1. **Test behavior, not implementation** — Verify what the code does from a caller's perspective, not how it achieves the result internally.
2. **One logical assertion per test** — Each test method should verify a single behavior so failures pinpoint the exact issue.
3. **Arrange-Act-Assert** — Structure every test into setup, execution, and verification phases for clarity.
4. **Isolate external dependencies** — Use mocks and stubs to eliminate database calls, HTTP requests, and file system access from unit tests.
5. **Use data providers for parameterization** — Leverage `#[DataProvider]` to test multiple input/output combinations without duplicating test methods.
6. **Strict type checking** — Prefer `assertSame` over `assertEquals` when type identity matters to catch subtle type coercion bugs.

**Naming & Structure**

- Every test method must start with `testIt...` (e.g. `testItConstructs`, `testItCanRetrieveValueXy`) and have a `void` return type.
- The variable holding the class under test must always be named `$sut`.
- If the class under test has constructor parameters, include a dedicated `testItConstructs` method that only verifies the object can be created.
- When expecting exceptions, always assert the exception message as well. For messages built with `sprintf`, keep a similar syntax in the test.

**Namespaces**

- **Unit tests:** `Tests\Unit\{mirrored namespace}` — mirrors the `app/` namespace under `tests/Unit/`.
- **Feature / integration tests:** `Tests\Feature\{relevant sub-namespace}`.
- When extending an existing test file, preserve its current namespace.

**Coverage Attributes**

Always annotate test classes with the appropriate coverage attributes from `PHPUnit\Framework\Attributes`:

```php
#[CoversClass(MyClass::class)]
#[CoversTrait(MyTrait::class)]
#[CoversMethod(MyClass::class, 'methodName')]
```

> Interfaces are never tagged with `#[CoversClass]`.

**Data Providers**

- Name a single-use provider after its test method: `provideTestItDoesSomethingData`.
- Return a generator (`iterable`) using `yield 'descriptive label' => [values]`:

```php
public static function provideTestItDoesSomethingData(): iterable
{
    yield 'empty string'   => ['', null];
    yield 'no at sign'     => ['hello', null];
    yield 'valid address'  => ['a@b.com', 'a@b.com'];
}
```

**Assertions**

PHPUnit assertion methods are `static` — always call them as `static::assertSame()`, not `$this->assertSame()`.

**Fixtures**

Create fixture classes as separate files, one file per fixture. Place them in a sub-namespace next to the test class. For a test class `MyClassXyTest`, the fixtures live in `MyClassXyTest\MyClassXyTestFixtures\`.

**Best Practices**

- Use `assertSame` over `assertEquals` when type matters — `assertSame` catches `'1' !== 1` bugs.
- Use `setUp` and `tearDown` for shared setup and cleanup.
- Mock only external dependencies (repositories, HTTP clients, third-party APIs). Do not mock value objects or simple utilities.
- Test both the happy path and error paths; unverified exception handling fails silently in production.

**Anti-Patterns to Avoid**

- Testing private methods via reflection — test through the public API instead.
- Over-mocking — mocking everything makes tests prove nothing about real behavior.
- Hardcoding absolute file paths — use `sys_get_temp_dir()` / `tempnam()`.
- Large test methods (> ~20 lines) — split into focused single-behavior tests.

#### PHPStan — Static Analysis

PHPStan is used to catch type errors and other issues statically, without running the code. Run it before every commit alongside the tests.

```bash
# In Docker
bin/env test php stan

# Locally
composer run test:stan
```

If PHPStan reports errors, fix them rather than suppressing them — suppressions should be a last resort reserved for genuine false positives in third-party code.

---

### Frontend / JS

There is currently no automated frontend test suite. Frontend testing will be introduced in **HAWKI 3.0.0**.

---

## Frontend Code

> **Planned Svelte rewrite:** The HAWKI frontend is planned to be rewritten as a full Svelte SPA. This section describes the first step in that direction. We are taking a **hybrid approach**: Blade templates remain the leading rendering layer, but we are progressively migrating UI sections into Svelte components that will later become part of the main SPA. **Do not add new code to the legacy vanilla-JS layer** (`public/js/`). All new frontend work must follow the patterns described here.

### Technology Stack

- **[Svelte 5](https://svelte.dev/)** with the Runes API (`$state`, `$derived`, `$props`, …) — no Options API / legacy Svelte 4 syntax
- **TypeScript** — every `.svelte` and `.ts` file must be typed; avoid `any` where possible
- **Vite** as the bundler (configured in `vite.config.js` / `svelte.config.js`)
- **CSS custom properties + cascade layers** — design tokens in `resources/css/tokens/`, component styles in Svelte `<style>` blocks; no Tailwind, no CSS-in-JS
- **`class-variance-authority` (CVA)** — declarative variant→class mapping for components that expose style-driving props (`size`, `intent`, …); `cx` re-exported from CVA is used internally by `mergeProps` for class merging

### Directory Structure

```
resources/js/
├── svelte/
│   ├── components/       ← Reusable, general-purpose Svelte components
│   │   └── ui/           ← Low-level primitive components (shadcn-style, no business logic)
│   ├── snippets/         ← Top-level Blade-embeddable snippet components (one per page slot)
│   ├── stores/           ← Svelte 5 reactive store classes (*.svelte.ts)
│   ├── types/            ← Shared TypeScript type definitions
│   │   ├── ai.ts         ← AI model / system prompt resource types
│   │   ├── connection.ts ← ConnectionConfig and related types
│   │   └── translation.ts← Locale and translation types
│   └── svelteSnippetLoader.ts ← Custom-element bridge for Blade integration
└── util/
    ├── hawkiConnection.ts ← Access to the server-rendered connection data blob
    ├── translator.ts      ← Client-side translation helper (mirrors Laravel's Translator)
    └── fileIconSvg.ts     ← File-type icon helper
```

### The Hybrid Approach: Snippets

Until the full SPA rewrite is complete, Svelte is integrated into the server-rendered Blade UI through the concept of **Snippets**. A Snippet is a regular Svelte component that is mounted inside a server-rendered Blade template, acting as a self-contained "mini-app" for a specific section of the page. Over time, these Snippets will grow into the building blocks of the final SPA.

This is a transitional architecture. The patterns below describe how to work within it correctly.

#### Embedding Svelte in Blade: the `<x-svelte>` Component

The bridge between Blade and Svelte is the `<x-svelte>` Blade component (implemented in `app/Services/Frontend/Connection/View/SvelteComponent.php`). It renders a `<svelte-snippet>` custom HTML element, which the `svelteSnippetLoader.ts` picks up and mounts the matching Svelte component inside.

```blade
{{-- Minimal --}}
<x-svelte type="ChatInput" />

{{-- With PHP props and extra HTML attributes --}}
<x-svelte
    type="ChatInput"
    :props="['readonly' => true]"
    class="my-class"
/>
```

The `type` attribute is the filename of the Svelte component inside `resources/js/svelte/snippets/`, without the `.svelte` extension. Props are JSON-encoded by the Blade component automatically. Any extra HTML attributes (`class`, `id`, `data-*`, …) are forwarded verbatim to the rendered element.

On the JavaScript side, the custom element is registered once via `registerSvelteSnippetLoader()` (called from `resources/js/app.js`). It discovers all files in `snippets/` automatically at build time using Vite's `import.meta.glob`, so **no manual import or registration is needed** when you add a new snippet.

**Lifecycle:** the component is mounted when the element enters the DOM, destroyed when it leaves, and destroyed + remounted whenever the `type` or `props` attribute changes at runtime. Treat snippets as stateless from the outside — internal state is reset on every remount.

#### Adding a New Snippet

1. Create a `.svelte` file in `resources/js/svelte/snippets/`, e.g. `resources/js/svelte/snippets/MyWidget.svelte`.
2. Use it in Blade: `<x-svelte type="MyWidget" />`.

No imports or registrations are needed anywhere else.

#### The `root` Prop

Every snippet automatically receives a `root` prop that is a reference to the `<svelte-snippet>` DOM element itself. Use it to:

- Read additional HTML attributes set by Blade
- Dispatch custom DOM events to communicate state changes back to legacy vanilla-JS code

```svelte
<script lang="ts">
    import {HTMLSvelteSnippetElement} from '../svelteSnippetLoader.js';

    interface Props {
        root: HTMLSvelteSnippetElement;
    }

    const {root}: Props = $props();

    function notifyLegacy(value: string) {
        root.dispatchEvent(new CustomEvent('myWidget:change', {detail: {value}, bubbles: true}));
    }
</script>
```

### Accessing Server Data: `hawkiConnection`

> **Temporary API:** `hawkiConnection` is a stopgap that reads data injected by the server into the initial page HTML. It will be replaced by a more robust mechanism as part of the SPA rewrite.

The backend injects a JSON blob into the page as a `<script id="frontend-connection">` element. Access it via the `hawkiConnection` utility from `resources/js/util/hawkiConnection.ts`:

```ts
import {hawkiConnection} from '../../util/hawkiConnection.js';

// Full config object
const config = hawkiConnection();

// Single top-level key
const aiConfig = hawkiConnection('ai');

// Dot-notation path
const mimeTypes = hawkiConnection('storage.allowedMimeTypes') as string[];
```

The return type is derived from `InternalConnectionConfig` (defined in `resources/js/svelte/types/connection.ts`). Add new fields there when the backend exposes new data.

### Translations

Use the `translate` helper from `resources/js/util/translator.ts`. It mirrors Laravel's `Translator::makeReplacements()` behaviour, including `:placeholder`, `:Placeholder`, `:PLACEHOLDER` casing variants and tag-callback replacements:

```ts
import {translate} from '../../util/translator.ts';

translate('chat.send_button');
translate('errors.file_too_large', {size: '10 MB'});
translate('room.invite', {name: (inner) => `<strong>${inner}</strong>`});
```

Translation keys are sourced from the `translation.labels` entry in the connection data blob, which the backend populates from the language JSON files in `resources/language/`.

### Reactive Stores

Shared reactive state lives in `resources/js/svelte/stores/` as plain TypeScript classes using Svelte 5 Runes (`$state`, `$derived`). Store files use the `.svelte.ts` extension so the Svelte compiler processes the runes.

Each store file exports both the class and a pre-constructed singleton instance:

```ts
// resources/js/svelte/stores/MyStore.svelte.ts
export class MyStore {
    public count = $state(0);
    public doubled = $derived(this.count * 2);
}

export const myStore = new MyStore();
```

Import the singleton in any component:

```svelte
<script lang="ts">
    import {myStore} from '../stores/MyStore.svelte.js';
</script>

<p>Count: {myStore.count}</p>
```

> Note the `.js` extension in imports — Vite resolves `.svelte.ts` files when a `.js` extension is used, which is the standard TypeScript ESM convention.

### Types

All shared TypeScript types live in `resources/js/svelte/types/`. The key files are:

| File             | Contents                                                                                 |
|------------------|------------------------------------------------------------------------------------------|
| `ai.ts`          | `AiModelResource`, `SystemModelResource`, `SystemPromptResource`, capability/tool labels |
| `connection.ts`  | `InternalConnectionConfig`, `CommonConnectionConfig`, route types                        |
| `translation.ts` | `Locale`, `LocaleCode`, `LocaleRecord`                                                   |

Extend these files when new data shapes are needed rather than defining one-off local interfaces in component files.

### Component Documentation

Every Svelte component must carry a `@component` block comment immediately before the `<script>` tag. This comment is picked up by tooling (e.g. VS Code Svelte extension) and shown in hover tooltips:

```svelte
<!--
  @component General description of what this component does and when to use it.
-->
<script lang="ts">
```

All props must be documented with a JSDoc comment inside the `Props` interface. Mark deprecated props with `@deprecated` and include a migration hint.

`Props` must always extend the appropriate `HTMLAttributes` type from `svelte/elements` so that TypeScript accepts standard HTML attributes (e.g. `class`, `id`, `aria-*`) on the component without explicit redeclaration:

```svelte
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /**
         * Description of what this prop does.
         */
        requiredProp: string;
        /**
         * Description of this optional prop.
         * @deprecated — use `requiredProp` instead.
         */
        optionalProp?: string;
    }

    const { requiredProp, optionalProp, ...rest }: Props = $props();
</script>
```

#### Resolving conflicting attribute types

Sometimes a component prop shares a name with an attribute already defined on the HTML element but with an incompatible signature — for example, overriding `onchange` to accept a domain-specific value instead of a raw `Event`. TypeScript will reject the override directly, so use an intermediate interface that widens the conflicting member to `any` first, then narrow it in `Props`:

```svelte
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';

    interface NonConflictingProps extends HTMLAttributes<HTMLDivElement> {
        onchange?: any; // widen to any so Props can redefine it safely
    }

    interface Props extends NonConflictingProps {
        /**
         * Executed when the selected value of the radio group changes.
         * @param newValue The newly selected value.
         */
        onchange?: (newValue: string) => void;
    }

    const { onchange, ...rest }: Props = $props();
</script>
```

#### Unique ID Generation

Components that need a stable `id` for accessibility (e.g. `<label for="...">`, `aria-describedby`) should generate one with `$props.id()` and fall back to any explicitly provided `id` prop:

```svelte
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Explicit id — generated automatically if omitted. */
        id?: string;
        label?: string;
    }

    const {id, label, ...restProps}: Props = $props();

    const generatedId = $props.id();
    const finalId = id || generatedId;
</script>

<div {...restProps}>
    <label for={finalId}>{label}</label>
    <input id={finalId} />
</div>
```

`$props.id()` is stable across renders for the same component instance and guaranteed unique across all instances — never use `Math.random()` or a module-level counter for this purpose.

### Component Organisation

- **`snippets/`** — top-level entry points, one per embedded page slot. Keep them thin: pull state from stores and delegate rendering to components.
- **`components/`** — reusable building blocks used by multiple snippets. A component should have no knowledge of which snippet uses it.
- **`components/ui/`** — low-level primitive components (buttons, inputs, links, chips, …) with no business logic and no dependency on app state or domain types. Modelled after the shadcn/ui pattern: each file is a single focused primitive that higher-level components in `components/` compose. Snippets import from `components/`, not directly from `ui/`, unless the usage is trivially simple.
- **`stores/`** — all reactive state that crosses component boundaries. Components read from and write to stores; they do not pass callbacks between siblings.

### `mergeProps` — Prop Merging

`mergeProps` (in `resources/js/util/mergeProps.ts`) is the standard way to forward rest-props onto a root element while keeping component-owned defaults. It accepts up to 6 objects and applies these merge rules:

| Key type             | Merge behaviour                                               |
|----------------------|---------------------------------------------------------------|
| `on*` event handlers | Both handlers are called in sequence — neither is overwritten |
| `class`              | Accumulated into an array; falsy entries filtered out         |
| Everything else      | Last value wins (standard overwrite)                          |

```svelte
<script lang="ts">
    import {mergeProps} from '../../util/mergeProps.js';
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLDivElement> {}
    const {...restProps}: Props = $props();

    let focused = $state(false);
</script>

<!--
  restProps spreads first so the component's own handlers/classes come last
  and win for non-event, non-class keys. Events and classes are always merged
  regardless of order.
-->
<div {...mergeProps(
    restProps,
    {
        class: ['my-component', focused && 'my-component--focused'],
        onfocus: () => { focused = true; },
        onblur:  () => { focused = false; },
    }
)}>
```

**Spread order matters** for non-event, non-class props: put `restProps` first so component-internal values take precedence as the last argument.

Use `cx` (re-exported from `class-variance-authority`) directly when you only need ad-hoc class merging without a full `mergeProps` call:

```ts
import {cx} from 'class-variance-authority';
const cls = cx('base', isActive && 'active', className);
```

### `$bindable()` — Two-Way Binding

Form and input components expose their value for two-way binding using the `$bindable()` rune. Declare the bindable prop with a sensible default:

```svelte
<script lang="ts">
    interface Props {
        /** Current text value. Supports bind:value. */
        value?: string;
        /** Toggle state. Supports bind:checked. */
        checked?: boolean;
    }

    const {
        value = $bindable(''),
        checked = $bindable(false),
    }: Props = $props();
</script>

<input bind:value={value} />
<input type="checkbox" bind:checked={checked} />
```

Callers use standard Svelte binding syntax:

```svelte
<MyInput bind:value={localVar} />
```

**Rules:**

- Only use `$bindable()` for values the parent genuinely needs to read back (form field values, toggle states). Props that only flow downward stay as plain props.
- Always provide a default inside `$bindable(default)` so the component works without a binding.
- For grouped inputs (checkbox groups), bind an array: `value = $bindable([])`.

### `SnippetOrString` — Polymorphic Content Props

When a prop can be either a plain string or a rich Svelte Snippet (e.g. `label`, `description`, `error`), type it as `string | Snippet` and render both cases:

```svelte
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        /** Plain text or a snippet for rich content. */
        label?: string | Snippet;
        /** Validation error message or snippet. */
        error?: string | Snippet;
    }

    const {label, error}: Props = $props();
</script>

{#if label}
    {#if typeof label === 'string'}
        <span>{label}</span>
    {:else}
        {@render label()}
    {/if}
{/if}
```

When the same pattern appears in multiple components, extract it into a small utility component (e.g. `components/ui/SnippetOrString.svelte`) to avoid repetition. The utility can be generic to pass typed arguments into the snippet:

```svelte
<!-- components/ui/SnippetOrString.svelte -->
<script lang="ts" generics="T">
    import type {Snippet} from 'svelte';
    interface Props { value: string | Snippet<[T | undefined]>; snippetArgs?: T; }
    const {value, snippetArgs}: Props = $props();
</script>
{#if typeof value === 'string'}{value}{:else}{@render value(snippetArgs)}{/if}
```

### Runed `Context` — Parent-Child Communication

Use Runed's `Context` class (from the `runed` package) instead of Svelte's built-in `setContext` / `getContext` when a parent component needs to share reactive state with deeply nested children without prop-drilling (e.g. a radio group sharing its selected value with individual radio card children).

Define the context in a dedicated file:

```ts
// RadioCardContext.ts
import {Context} from 'runed';

interface RadioCardContext {
    getValue: () => string;
    setValue: (newValue: string) => void;
    isDisabled: () => boolean;
}

export const radioCardContext = new Context<RadioCardContext>('radio-card');
```

**Parent** — set the context once, exposing getter/setter functions so children always read current state:

```svelte
<!-- RadioCardGroup.svelte -->
<script lang="ts">
    import {radioCardContext} from './RadioCardContext.js';

    const {disabled = false, onchange}: Props = $props();
    let selected = $state('');

    radioCardContext.set({
        getValue:   () => selected,
        setValue:   (v) => { selected = v; onchange?.(v); },
        isDisabled: () => disabled,
    });
</script>
<slot />
```

**Child** — call the getter functions inside the template so Svelte's reactivity tracks them:

```svelte
<!-- RadioCard.svelte -->
<script lang="ts">
    import {radioCardContext} from './RadioCardContext.js';
    const {value}: Props = $props();
    const ctx = radioCardContext.get();
</script>

<button
    aria-pressed={ctx.getValue() === value}
    disabled={ctx.isDisabled()}
    onclick={() => ctx.setValue(value)}
>
    <slot />
</button>
```

Wrapping mutable values in **getter functions** (rather than storing plain values in the context object) is required for reactivity: Svelte re-evaluates template expressions that call the function whenever the underlying `$state` in the parent changes. The string name passed to `new Context(name)` aids debugging in Svelte DevTools.

### Link — Accessible Anchor Primitive

`components/ui/Link.svelte` is the standard anchor component. Always use it instead of a bare `<a>` tag when you need:

- Automatic `rel="noopener noreferrer"` on `target="_blank"` links (prevents tabnabbing)
- A `disabled` state that blocks navigation without removing the element from the DOM
- A consistent `disabled` CSS class for styling

```svelte
<Link href="/dashboard">Dashboard</Link>

<!-- rel set automatically -->
<Link href="https://example.com" target="_blank">External link</Link>

<!-- navigation blocked, disabled class applied -->
<Link href="/action" disabled>Unavailable</Link>
```

**Props:**

| Prop       | Type      | Default | Description                                                                                                   |
|------------|-----------|---------|---------------------------------------------------------------------------------------------------------------|
| `href`     | `string`  | `''`    | Navigation target. Set to `javascript:void(0)` when empty or `disabled`.                                      |
| `target`   | `string`  | `''`    | Standard anchor `target`.                                                                                     |
| `rel`      | `string`  | `''`    | Overrides the automatic `rel`. Defaults to `noopener noreferrer` when `target="_blank"` and `rel` is not set. |
| `disabled` | `boolean` | `false` | Prevents navigation and applies a `disabled` class.                                                           |
| `children` | `Snippet` | —       | Link content.                                                                                                 |

All other `HTMLAnchorAttributes` (`class`, `aria-*`, `data-*`, …) are forwarded via rest-props. `href`, `rel`, and `onclick` are computed with `$derived.by()` so they react to `disabled` and `target` changes. Attributes with no value (empty `target`, empty `rel`, no `onclick`) are omitted from the rendered `<a>` to keep the HTML clean.

---

## Styling

### Architecture

The project uses a **CSS cascade layer system** to give explicit control over specificity. Layers are declared once in `resources/css/app.css`:

```
@layer reset, tokens, base, components, utilities;
```

Priority (lowest → highest): `reset` < `tokens` < `base` < `components` < `utilities`. This eliminates all need for `!important` — specificity is explicit and intentional.

All design values — colors, spacing, typography, radii, shadows, transitions — are defined as CSS custom properties in `resources/css/tokens/`. Svelte scoped `<style>` blocks compile into the `components` layer automatically.

```
resources/css/
├── app.css                   entry point: @layer declaration + imports
├── tokens/
│   ├── colors.css            OKLCH color scales + semantic aliases
│   ├── typography.css        font sizes, weights, line heights
│   ├── spacing.css           --space-1 through --space-16
│   ├── radius.css            --corner-sm / md / lg / full
│   ├── shadows.css           --elevation-none / 1 / 2
│   └── transitions.css       --duration-* and --easing-*
└── layers/
    ├── reset.css             minimal modern reset
    └── base.css              body, focus ring, scrollbar defaults
```

Dark mode is toggled via `[data-theme="dark"]` on `<html>`, with `@media (prefers-color-scheme: dark)` as an OS-level fallback. All color tokens update automatically — **components need no dark-mode-specific rules of their own**.

### Token Reference

All tokens are available as CSS custom properties on every element. Common groups:

| Group       | Example tokens                                                                                                 |
|-------------|----------------------------------------------------------------------------------------------------------------|
| Colors      | `--color-bg`, `--color-surface`, `--color-text`, `--color-text-muted`, `--color-interactive`, `--color-border` |
| Typography  | `--font-size-xs` → `--font-size-2xl`, `--font-weight-medium`, `--line-height-normal`                           |
| Spacing     | `--space-1` (4px) → `--space-16` (64px)                                                                        |
| Radius      | `--corner-sm` (5px), `--corner-md` (10px), `--corner-lg` (30px), `--corner-full`                               |
| Shadows     | `--elevation-none`, `--elevation-1`, `--elevation-2`                                                           |
| Transitions | `--duration-fast` (300ms), `--duration-normal`, `--easing-default`, `--easing-spring`                          |

The full list of available tokens lives in the individual files under `resources/css/tokens/`.

### Breakpoints

Breakpoints are defined as [CSS Custom Media Queries](https://www.w3.org/TR/mediaqueries-5/#custom-mq) in `resources/css/tokens/breakpoints.css` and processed by [`postcss-custom-media`](https://github.com/csstools/postcss-plugins/tree/main/plugins/postcss-custom-media). They are made globally available across all CSS files (including Svelte `<style>` blocks) via `@csstools/postcss-global-data`.

| Range | Min    | Max    |
|-------|--------|--------|
| `xxs` | 0      | 300px  |
| `xs`  | 0      | 549px  |
| `sm`  | 550px  | 767px  |
| `md`  | 768px  | 991px  |
| `lg`  | 992px  | 1199px |
| `xl`  | 1200px | —      |

Each range exposes several named queries:

| Query                       | Matches                          |
|-----------------------------|----------------------------------|
| `--bp-{range}`              | Exactly that range               |
| `--bp-{range}-and-smaller`  | That range and below             |
| `--bp-{range}-and-bigger`   | That range and above             |
| `--bp-smaller-than-{range}` | Everything below the range's min |
| `--bp-bigger-than-{range}`  | Everything above the range's max |
| `--bp-mode-mobile`          | `max-width: 850px`               |
| `--bp-mode-desktop`         | `min-width: 851px`               |

```css
/* In any .svelte <style> block or .css file */
@media (--bp-md-and-bigger) {
    .sidebar {
        display: flex;
    }
}

@media (--bp-sm-and-smaller) {
    .nav {
        flex-direction: column;
    }
}
```

PostCSS expands these to standard `@media` queries at build time — no browser support concerns.

### Writing Component Styles

Write all component styles in the `<style>` block of the `.svelte` file. Svelte scopes them automatically — no BEM class naming is needed to prevent leakage between components.

There are two levels of token use inside a component:

1. **Reference globals directly** for properties that are set once and never vary across states: `border-radius: var(--corner-md)`, `padding: var(--space-6)`.
2. **Declare a component-local token** at the root element of the component (the outermost DOM element — *not* CSS `:root`) for any value that either appears in multiple places *or* changes under a state rule. Reassigning the local token in a state rule then propagates the change to every property referencing it automatically, so each state override collapses to the minimum number of lines.

If you want the component to be externally restylable (e.g. a reusable primitive), the fallback form `var(--card-elevation, var(--elevation-1))` lets a parent pass `--card-elevation` to customise without needing to pierce Svelte's scope.

```svelte
<!-- resources/js/svelte/components/Card.svelte -->
<script lang="ts">
    interface Props {
        title: string;
        children?: import('svelte').Snippet;
    }
    const { title, children }: Props = $props();
</script>

<div class="card">
    <h2 class="card__title">{title}</h2>
    {#if children}
        <div class="card__body">{@render children()}</div>
    {/if}
</div>

<style>
    /*
     * Declare a component-local token at the root element of the component
     * (the outermost DOM element, not CSS :root) when the value either:
     *   - appears in multiple properties, or
     *   - needs to change under a state rule (:hover, :focus, [disabled], …)
     * For single-use, never-changing values, reference the global token directly.
     */
    .card {
        --card-bg:        var(--color-surface);
        --card-border:    var(--color-border);
        --card-elevation: var(--elevation-1);

        background:    var(--card-bg);
        border:        1px solid var(--card-border);
        border-radius: var(--corner-md);         /* single-use — global token directly */
        box-shadow:    var(--card-elevation);
        padding:       var(--space-6);            /* single-use — global token directly */
        transition:    box-shadow var(--duration-fast) var(--easing-default);
    }

    /*
     * State rules reassign local tokens only — never repeat property declarations.
     * The browser re-evaluates every property referencing the token automatically,
     * so each state collapses to the minimum number of lines.
     */
    .card:hover {
        --card-border:    var(--color-border-strong);
        --card-elevation: var(--elevation-2);
    }

    .card__title {
        font-size:     var(--font-size-lg);
        font-weight:   var(--font-weight-bold);
        color:         var(--color-text);
        margin-bottom: var(--space-3);
    }

    .card__body {
        color:     var(--color-text-muted);
        font-size: var(--font-size-sm);
    }
</style>
```

Because color tokens automatically switch values under `[data-theme="dark"]`, this component works correctly in both themes with no additional CSS.

### Variant Components (CVA)

When a component exposes props that drive visual style (`size`, `intent`, `weight`, …), use `cva` from `class-variance-authority` instead of manually constructing class strings. This keeps the variant→class mapping declarative and gives you type-safe prop types for free:

```svelte
<script lang="ts">
    import {cva, type VariantProps} from 'class-variance-authority';
    import {mergeProps} from '../../util/mergeProps.js';
    import type {HTMLAttributes} from 'svelte/elements';

    const buttonVariants = cva('btn', {
        variants: {
            intent: {primary: 'btn--primary', secondary: 'btn--secondary'},
            size:   {sm: 'btn--sm', md: 'btn--md'},
        },
        defaultVariants: {intent: 'primary', size: 'md'},
    });

    interface Props extends HTMLAttributes<HTMLButtonElement> {
        intent?: VariantProps<typeof buttonVariants>['intent'];
        size?:   VariantProps<typeof buttonVariants>['size'];
    }

    const {intent, size, ...restProps}: Props = $props();

    const elementProps = $derived(mergeProps(
        {class: buttonVariants({intent, size})},
        restProps
    ));
</script>
```

`VariantProps<typeof buttonVariants>` automatically reflects the valid values from the definition — no manual union types needed. `defaultVariants` eliminates `?? 'fallback'` chains. Use `cx` (re-exported from `class-variance-authority`) directly when you need ad-hoc class merging without full variant definitions.

### Rules

- **No `!important`** — ever. Cascade layers make it unnecessary.
- **No hardcoded colors** — always reference a token. If no suitable token exists, add one to `resources/css/tokens/colors.css`.
- **No hardcoded sizes** — use spacing, radius, or typography tokens.
- **States reassign component-local tokens**, not global ones. Because the browser re-evaluates every property referencing the token automatically, one reassignment line replaces what would otherwise be repeated property declarations in every state rule.
- **No utility-class spam** — if a pattern repeats across 3+ components, extract a shared Svelte primitive, not a utility class.
- **Dark mode is free** — do not add `[data-theme="dark"]` rules inside component styles. The token layer handles it globally.

> **Migration note:** Legacy styles in `public/css/` continue to load alongside the new system during the transition to Svelte. New components must use the token system above. Do not add new rules to the legacy files.

---

## Pull Request Process

### Before Creating a PR

1. Ensure your branch is up to date with `development`
2. Review your own changes
3. Run the test suite locally (automated test coverage is actively being built out — check for new tests before assuming there are none)

The release pipeline and automated checks are described in [`_changelog/README.md`](https://github.com/hawk-digital-environments/HAWKI/blob/development/_changelog/README.md).

### PR Scope & Size

One PR = one responsibility. Keep PRs small and focused:

- One feature, bugfix, or refactor (or a tightly related set)
- Do not mix refactors, formatting, and feature changes
- If a change touches many files, explain why in the description

### PR Title & Description

Use the same format as commit messages:

```
feat(ai): add model status caching
fix(auth): resolve LDAP reconnect loop
```

A good PR description answers:

- **What** was changed?
- **Why** was this approach chosen?
- What issue does it close? (`Closes #123`)

### Draft PRs

For early feedback or architectural guidance, open a Draft PR and request specific feedback in the description. Mark as "Ready for review" when complete.

---

## Code Review

### For Contributors

- All PRs require at least one approval before merge
- Address all feedback; resolve conversations when done
- If feedback is unclear, ask for clarification

### For Reviewers

- Critique code, not people
- Explain *why*, not just *what*
- Suggest alternatives where relevant
- Label comments:
    - **Blocking:** "This will cause a bug because..."
    - **Non-blocking:** "Consider X for better readability"
    - **Question:** "Why did you choose this approach?"

---

## AI Agents

You are welcome to use AI tools when contributing to HAWKI. AI assistants can help you:

- Write and review code following our architecture and standards
- Generate tests following PHPUnit best practices
- Format code and catch common errors
- Understand documentation and coding patterns
- Debug issues and optimize performance

To help you succeed, we provide curated skills:

- **[HAWKI backend Skill](pathname://attachments/skills/hawki-backend/SKILL.md)** — The skill for working at our Laravel backend layer, including our architecture, coding standards, and best practices
- **[HAWKI frontend Skill](pathname://attachments/skills/hawki-frontend/SKILL.md)** — The skill for working at our Svelte frontend layer, including our hybrid architecture, coding standards, and best practices
- **[PHPUnit Testing Skill](pathname://attachments/skills/phpunit/SKILL.md)** — Comprehensive guidance for writing effective unit and feature tests, including assertions, data providers, mocking, and test structure

Share these skills with your AI tool to provide context on HAWKI's expectations.

---

## Getting Help

- **[GitHub Issues](https://github.com/hawk-digital-environments/HAWKI/issues)** — Bugs and feature requests
- **[Discord](https://discord.gg/zzR54sRWDE)** — Real-time support in **#sos-support**
- **[Documentation](https://docs.hawki.info)** — Guides and FAQs

**Good First Issues:** Look for `good first issue`, `help wanted`, or `documentation` labels.

When in doubt about architecture, open a Draft PR early rather than building something that might need a major rewrite. Architectural discussions are cheaper than large rewrites.

---

## Philosophy

- **Clarity over cleverness** — Simple, readable code wins
- **Explicit dependencies** — Make dependencies visible and testable
- **Domains, not layers** — Organize by business concept, not technical concern
- **Consistency** — Follow established patterns in the codebase
- **Incremental improvement** — Small, focused changes compound over time

---

> **A note of honest self-deprecation:** We are aware that the current codebase does not fully reflect the goals described in this guide — folder names are inconsistent, a few `Utils/` directories exist that shouldn't, and some naming conventions are mid-migration. We are actively working to bring the code in line with these rules across upcoming releases. Please do as we say, not as we did. :)

Thank you for contributing to HAWKI! 🧡
