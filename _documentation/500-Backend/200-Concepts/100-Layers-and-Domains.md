# Layers & Domains

HAWKI's layer responsibilities and the lightweight DDD layout under `app/Services/`. Read this first if you are new to the codebase.

## Layer responsibilities

| Layer           | Responsibility                                         | Must not                             |
|-----------------|--------------------------------------------------------|--------------------------------------|
| **Controller**  | Receive HTTP, delegate to a service, return a response | Contain business logic, query the DB |
| **FormRequest** | Validate and authorize the request shape               | Access services or repositories      |
| **Service**     | Orchestrate domain workflows                           | Access HTTP, sessions, facades       |
| **Repository**  | Issue DB queries via Eloquent                          | Contain business logic               |
| **Model**       | Declare structure, relationships, casts                | Contain business logic, use facades  |

When a layer rule and convenience conflict, the rule wins. A controller that queries the DB "just this once" teaches the next reader the wrong pattern.

## Domain-Driven Design (light)

HAWKI does not do pure DDD. The "light" variant:

- Business logic lives in domains under `App\Services\{DomainName}\`.
- Laravel-native classes (Controllers, Models, FormRequests) stay in their conventional `app/` locations.
- Domain events and listeners live inside the domain, not in a global `app/Events/` folder. If you find an `app/Events/` root, it is a legacy artifact.

The signal to read more: if code feels cross-cutting or unclear about where it belongs, the answer is almost always a domain service, not a utility class.

## Domain directory anatomy

```
app/Services/Ai/
├── Contracts/          interfaces for cross-domain communication
├── Events/             domain events; may have sub-namespaces for grouping
├── Exceptions/         domain-specific exceptions
├── Listeners/          event listeners; auto-discovered by bootstrap
├── Repositories/       database access only
│   └── Queries/        optional focused query objects
├── Values/             value objects, DTOs, enums
├── AiFactory.php       named collaborator at the domain root
└── AiService.php       domain public API (@api)
```

Structural directories (`Contracts/`, `Values/`, `Exceptions/`, `Repositories/`) use plural names. `Utils/` is a classification failure — every class has a more precise home.

Named collaborators that are direct, single-purpose partners of the domain service live flat at the domain root alongside the service (e.g. `AiFactory` next to `AiService`).

## Naming conventions

- Namespace segments are `CamelCase`, including acronyms: `Ai` not `AI`, `Mcp` not `MCP`, `Http` not `HTTP`.
- `...Service` classes always live at the domain root, never inside a structural namespace.
- Any class named `...Service` is the public API of its domain and must carry `@api`. See [API Stability](./210-API-Stability.md).
- Prefer `Contracts/` over `Interfaces/`.

Service decomposition (sub-services vs traits) and singleton binding are covered in [Dependency Injection](./110-Dependency-Injection/index.md).
