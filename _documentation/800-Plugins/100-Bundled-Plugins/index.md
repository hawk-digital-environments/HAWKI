# Bundled Plugins

HAWKI's first-party plugins. Each plugin bundles one or more feature modules (chat, …) and registers the stores, schemas, routes, and migrations the feature needs.

Currently only the `core` plugin ships with HAWKI. The structure here is designed to hold more plugins as they land.

| Plugin | What it bundles |
|---|---|
| [core](100-Core/index.md) | The foundational, always-on features: chat module, core stores, core migrations, the root route. |

For how plugins are discovered and dispatched internally, see [Concepts → Plugin Internals](../../600-Frontend/200-Concepts/210-Plugins.md). For authoring a plugin, see [Extending HAWKI](../200-Extending-HAWKI/index.md).
