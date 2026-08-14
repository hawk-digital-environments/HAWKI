# Backend Overview

HAWKI's backend is a **Laravel 13 / PHP 8.3** application. It is in an active transition from a server-rendered MVC application to a pure API server that feeds a Svelte SPA. Most new work targets the API layer; Blade is read-only except for the page shell.

## How the docs are organised

| Section                                         | What you find there                                                                |
|-------------------------------------------------|------------------------------------------------------------------------------------|
| [Tutorials](./100-Tutorials/index.md)           | One concrete scenario, walked end-to-end. The cleanest path in the codebase.       |
| [Concepts](./200-Concepts/index.md)             | "How do I use pattern X" — one page per pattern. The hub everything else links to. |
| [HTTP API](./300-HTTP-API/index.md)             | Request/response contracts and conventions.                                        |
| [Domains](./400-Domains/index.md)               | What each domain *does*, not how patterns work.                                    |
| [Reference](./500-Reference/index.md)           | Lookup catalogues (artisan commands, shared utilities). Code is truth.             |
| [Infrastructure](./600-Infrastructure/index.md) | Cross-cutting runtime services that are neither patterns nor domains.              |
| [Roadmap](./700-Roadmap/index.md)               | Not-yet-implemented designs and deprecated transition scaffolding.                 |
| [Technical Debt](./900-Technical-Debt.md)       | The violations register, audience-tagged.                                          |

## Where things live

| Concern                           | Location                 |
|-----------------------------------|--------------------------|
| Domain business logic             | `app/Services/{Domain}/` |
| HTTP controllers                  | `app/Http/Controllers/`  |
| Form validation                   | `app/Http/Requests/`     |
| JSON:API v1 schemas and resources | `app/JsonApi/V1/`        |
| API Resources (serializers)       | `app/Http/Resources/`    |
| Eloquent models                   | `app/Models/`            |
| Shared utilities                  | `app/Utils/`             |
| System infrastructure             | `app/Services/System/`   |

The main external surface is the JSON:API v1 server at `/api/hawki/v1`. All AI interaction, room management, user keychain, and configuration endpoints live there. Some file-serving and authentication routes remain on `routes/web.php` as Blade-era endpoints.

## Where to start

| I want to…                                                     | Start here                                                                                                                                                                                         |
|----------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Contribute code** (new features, bug fixes)                  | [Concepts → Layers & Domains](./200-Concepts/100-Layers-and-Domains.md) → [Life of a Request](./100-Tutorials/100-Life-of-a-Request.md) → your domain section                                      |
| **Use a pattern** (repositories, scopes, events, …)            | [Concepts index](./200-Concepts/index.md) — the "I want to…" table routes you to the right page                                                                                                    |
| **Deploy or operate HAWKI** (configure, monitor, troubleshoot) | [Infrastructure](./600-Infrastructure/index.md) → [Artisan Commands](./500-Reference/100-Artisan-Commands.md) → [Encryption](./400-Domains/400-Encryption/410-Encryption.md) (salts in production) |
| **Extend HAWKI without touching core**                         | [Extending HAWKI](./200-Concepts/220-Extending-HAWKI.md) — every live extension point in one place                                                                                                 |
| **Build a plugin** (v3 plugin system)                          | [Roadmap — Plugin System](./700-Roadmap/100-Plugin-System.md) — not yet implemented                                                                                                                |
