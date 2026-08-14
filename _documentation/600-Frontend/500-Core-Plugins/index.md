# Core Plugins

HAWKI's first-party plugins. Each plugin bundles one or more feature modules (chat, …) and registers the stores, schemas, routes, and migrations the feature needs.

Currently only the `core` plugin ships with HAWKI. The structure here is designed to hold more plugins as they land — an `assistants` plugin is planned.

| Plugin | What it bundles |
|---|---|
| [core](100-Core/index.md) | The foundational, always-on features: chat module, core stores, core migrations, the root route. |

For how plugins are discovered and dispatched internally, see [Architecture → Plugin Internals](../300-Architecture/130-Plugin-Internals.md). For authoring a plugin, see [Extending HAWKI](../../700-Extending-Hawki/index.md).
