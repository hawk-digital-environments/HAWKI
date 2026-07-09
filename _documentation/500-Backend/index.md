# Backend Overview

HAWKI's backend is a **Laravel 13 / PHP 8.3** application. It is in an active transition from a server-rendered MVC application to a pure API server that feeds a Svelte SPA. Most new work targets the API layer, but the old Blade routes still exist and will stay until the frontend migration is complete.

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

## Domain-Driven Design (light)

HAWKI uses a lightweight variant of Domain-Driven Design. Business logic is organized by domain concept under `app/Services/` rather than by technical layer. Laravel-native classes (Controllers, Models, FormRequests) stay in their conventional locations; Events and Listeners live inside their domain under `App\Services\{Domain}\Events\` and `Listeners\`.

This means you will rarely see an `app/Events/` or `app/Listeners/` root folder with new code. If you encounter one, it is a legacy artifact.

## Why some abstractions feel heavier than they need to be

Several patterns in HAWKI — `@api` markers, `ProviderAdapterRegistry::declare()`, filter events, `DecoratorTrait`, `AbstractConfig` — feel heavier than their current use warrants. They are groundwork for the HAWKI v3 plugin system. The plugin system will allow third-party packages to extend HAWKI without modifying core code. These patterns establish the stable surface the plugin system will depend on.

See [Plugin System Preview](./1000-Infrastructure/100-Plugin-System-Preview.md) for what is planned.

## Where to start

| I want to…                                                     | Start here                                                                                                                                                                                        |
|----------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Contribute code** (new features, bug fixes)                  | [Architecture](./100-Architecture/index.md) → [Life of a Request](./150-Life-of-a-Request.md) → your domain section                                                                               |
| **Deploy or operate HAWKI** (configure, monitor, troubleshoot) | [Infrastructure](./1000-Infrastructure/index.md) → [Artisan Commands](./1000-Infrastructure/200-Artisan-Commands.md) → [Encryption](./800-Encryption-and-Security/index.md) (salts in production) |
| **Build a plugin** (extend HAWKI without touching core)        | [API Stability](./100-Architecture/100-API-Stability.md) → [Plugin System Preview](./1000-Infrastructure/100-Plugin-System-Preview.md) → the relevant domain's extension-point note               |
