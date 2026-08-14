# Architecture

The kernel is the small, extension-assembled core of the frontend. This section explains how the app is built, how it boots, how modules and routing work, and how plugins dispatch — for contributors who need the mental model.

| I want to… | Read |
|---|---|
| Get the mental model of the app + kernel | [The App & Kernel](100-App-and-Kernel.md) |
| Understand the boot sequence and stages | [App Startup](110-App-Startup.md) |
| Understand modules, routing, and the SPA shell | [Modules & Routing](120-Modules-and-Routing.md) |
| Understand how plugins are discovered and dispatched | [Plugin Internals](130-Plugin-Internals.md) |

Authoring a new plugin or extension is a separate concern that touches backend and frontend alike — see [Extending HAWKI](../../700-Extending-Hawki/index.md) for the how-to. These pages cover the kernel internals a contributor needs to understand what they're plugging into.
