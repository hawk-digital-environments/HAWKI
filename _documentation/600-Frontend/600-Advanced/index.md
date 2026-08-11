# Advanced

Deeper topics for contributors and the architecture-curious. The frontend is a custom kernel + plugin system; this section explains how it starts up, how the app is assembled, how to extend it, and how the remaining cross-cutting concerns (legacy bridge, encryption, migrations) work.

## Where to start

| I want to… | Read |
|---|---|
| Understand the boot sequence and stages | [App Startup](100-App-Startup.md) |
| Get the mental model of the app + kernel | [The App & Kernel](110-App-and-Kernel.md) |
| Add a new app-wide subsystem (extension) | [Writing an Extension](120-Writing-an-Extension.md) |
| Add a feature (stores, schemas, snippets, modules) | [Writing a Plugin](130-Writing-a-Plugin.md) |
| Know the status of frontend routing | [Routing](200-Routing.md) |
| Bridge new Svelte code to the legacy JS layer | [Old UI Integration](300-Old-Ui.md) |
| Work with client-side encryption | [Encryption](400-Encryption.md) |
| Create or understand a frontend migration | [Frontend Migrations](500-Frontend-Migrations.md) |

The pages are numbered in reading order: 100 → 110 → 120 → 130 top-to-bottom for the full architecture walkthrough, then whichever concern applies.
