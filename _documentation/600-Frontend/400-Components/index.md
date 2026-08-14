# Components

Catalogue of the Svelte component library under `resources/js/components/`. These are reusable building blocks — no feature owns them, no feature knowledge lives in them.

:::info[Planned extraction]
The `components/` directory is slated to be extracted into a dedicated npm package so extension authors can reuse the same primitives. Keep `components/ui/` free of app-state and domain-type dependencies so the extraction stays clean.
:::

| I want to… | Read |
|---|---|
| Find a primitive (button, dialog, popover, …) | [UI Primitives](100-UI-Primitives.md) |
| Find a utility component (Link, Markdown, Breakpoint, …) | [Utility Components](200-Utility-Components.md) |
| Use an icon | [Icons](210-Icons.md) |

For the conventions every component follows (`@component` blocks, `Props extends HTMLAttributes`, `mergeProps`, `$bindable`, context, unique IDs), see [Concepts → Svelte Components](../200-Concepts/100-Svelte-Components.md).
