# Svelte Components

> **Planned Svelte rewrite:** The HAWKI frontend is planned to be rewritten as a full Svelte SPA. This document describes the first step in that direction. We are taking a **hybrid approach**: Blade templates remain the leading rendering layer, but we are progressively migrating UI sections into Svelte components that will later become part of the main SPA. **Do not add new code to the legacy vanilla-JS layer** (`public/js/`). All new frontend work must follow the patterns described here.

---

## Technology Stack

- **[Svelte 5](https://svelte.dev/)** with the Runes API (`$state`, `$derived`, `$props`, …) — no Options API / legacy Svelte 4 syntax
- **TypeScript** — every `.svelte` and `.ts` file must be typed; avoid `any` where possible
- **Vite** as the bundler (configured in `vite.config.js` / `svelte.config.js`)
- **CSS custom properties + cascade layers** — design tokens in `resources/css/tokens/`, component styles in Svelte `<style>` blocks; no Tailwind, no CSS-in-JS
- **`class-variance-authority` (CVA)** — declarative variant→class mapping for components that expose style-driving props (`size`, `intent`, …); `cx` re-exported from CVA is used internally by `mergeProps` for class merging

---

## Directory Structure

```
resources/js/
├── components/       ← Reusable, general-purpose Svelte components
│   └── ui/           ← Low-level primitive components (no business logic)
├── snippets/         ← Top-level Blade-embeddable entry components (one per page slot)
├── stores/           ← Svelte 5 reactive store classes (*.svelte.ts)
├── types.ts          ← Shared TypeScript type definitions
└── utils/            ← Shared utilities
```

> **Note:** Earlier versions of the Contributing guide incorrectly documented these paths with an extra `svelte/` path segment (e.g. `resources/js/svelte/snippets/`). The correct paths do not include that segment.

---

## The Hybrid Approach — Snippets

Until the full SPA rewrite is complete, Svelte is integrated into the server-rendered Blade UI through the concept of **snippets**. A snippet is a regular Svelte component that is mounted inside a server-rendered Blade template, acting as a self-contained "mini-app" for a specific section of the page. Over time these snippets will grow into the building blocks of the final SPA.

### Why snippets are isolated

Each snippet is its own separately mounted Svelte application. There is no shared Svelte component tree or Svelte context across snippets on the same page. Two snippets rendered side by side in the DOM cannot communicate through `setContext`/`getContext` because they have different component roots.

**Stores cross snippet boundaries automatically.** Svelte stores implemented as module-level singletons (exported from a `.svelte.ts` file) are shared because JavaScript modules are singletons within a page load. Any snippet that imports the same store module reads and writes the same reactive instance.

### `AppContext` — simulated global context

`resources/js/components/app/AppContext.svelte.ts` works around the snippet isolation problem by maintaining a module-level singleton instead of using Svelte's real context mechanism. Any snippet can call `useAppContext()` to get the shared instance, even though there is no common Svelte ancestor.

This is explicitly marked `@deprecated`. It exists only to bridge the gap until the SPA rewrite replaces snippet-per-slot mounting with a single Svelte root. Once that happens, `AppContext` will be replaced by a proper `createAppContext()` that uses Svelte's real context API.

The main thing `AppContext` currently holds is a reference to the shared `ToastContext`, so the `Toaster` component can be mounted exactly once regardless of how many snippets are on the page.

### `LegacySharedContent.svelte` — the page-level singleton host

`resources/js/snippets/LegacySharedContent.svelte` is a special snippet that is auto-injected at the top of every page during bootstrap. Its job is to host UI elements that must exist exactly once per page but cannot live in every snippet independently.

On mount it sets `AppContext.legacySharedContentLoaded = true` and renders the shared `Toaster` component. If you attempt to push a toast when this snippet has not been loaded, the application throws rather than silently failing.

Any page-level singleton UI that faces the same "one instance per page" constraint belongs here, not inside a regular snippet.

### Currently active snippets

| Snippet | Purpose |
|---|---|
| `ChatComposer.svelte` | Main chat input: message composition, file attachments, model/tool selection |
| `ChatHeader.svelte` | Chat header bar with conversation controls |
| `ChatSidebarButton.svelte` | Sidebar toggle/open button |
| `AttachmentDropdown.svelte` | Attachment preview and management dropdown |
| `LegacySharedContent.svelte` | Auto-injected; hosts the shared Toaster and other page-level singletons |

### Embedding Svelte in Blade: the `<x-svelte>` component

The bridge between Blade and Svelte is the `<x-svelte>` Blade component (implemented in `app/Services/Frontend/Connection/View/SvelteComponent.php`). It renders a `<svelte-snippet>` custom HTML element, which `svelteSnippetLoader.ts` picks up and mounts the matching Svelte component inside.

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

The `type` attribute is the filename of the Svelte component inside `resources/js/snippets/`, without the `.svelte` extension. Props are JSON-encoded by the Blade component automatically. Any extra HTML attributes (`class`, `id`, `data-*`, …) are forwarded verbatim to the rendered element.

On the JavaScript side, the custom element is registered once via `registerSvelteSnippetLoader()` (called from `resources/js/app.ts`). It discovers all files in `snippets/` automatically at build time using Vite's `import.meta.glob`, so **no manual import or registration is needed** when you add a new snippet.

**Lifecycle:** the component is mounted when the element enters the DOM, destroyed when it leaves, and destroyed + remounted whenever the `type` or `props` attribute changes at runtime. Treat snippets as stateless from the outside — internal state is reset on every remount.

### Adding a new snippet

1. Create a `.svelte` file in `resources/js/snippets/`, e.g. `resources/js/snippets/MyWidget.svelte`.
2. Use it in Blade: `<x-svelte type="MyWidget" />`.

No imports or registrations are needed anywhere else.

### The `root` prop

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

---

## Accessing Server Data

Use `getConfig()` from `resources/js/data/config/config.ts` for runtime configuration values, and the `getConnection()` family from `resources/js/data/connection/connection.ts` for API calls. Both are available only after the `preparation` boot stage completes. See the Data Layer documentation for the full API reference.

---

## Translations

Use the `translate` helper from `resources/js/utils/translator.ts`. It mirrors Laravel's `Translator::makeReplacements()` behaviour, including `:placeholder`, `:Placeholder`, `:PLACEHOLDER` casing variants and tag-callback replacements:

```ts
import {translate} from '../utils/translator.ts';

translate('chat.send_button');
translate('errors.file_too_large', {size: '10 MB'});
translate('room.invite', {name: (inner) => `<strong>${inner}</strong>`});
```

Translation keys are sourced from the `translation.labels` entry in the connection data blob, which the backend populates from the language JSON files in `resources/language/`.

---

## Reactive Stores

Shared reactive state lives in `resources/js/stores/` as plain TypeScript classes using Svelte 5 Runes (`$state`, `$derived`). Store files use the `.svelte.ts` extension so the Svelte compiler processes the runes.

Each store file exports both the class and a pre-constructed singleton instance:

```ts
// resources/js/stores/MyStore.svelte.ts
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

---

## Types

All shared TypeScript types live in `resources/js/types.ts`. The key file is:

| File | Contents |
|---|---|
| `types.ts` | All shared type definitions: AI model resources, connection config, locale and translation types, and more |

Extend this file when new data shapes are needed rather than defining one-off local interfaces in component files.

---

## Available UI Primitives (`components/ui/`)

These are low-level primitives with no business logic. Compose them into higher-level components; do not import directly from `ui/` in snippets unless the usage is trivially simple.

| Component(s) | Directory / File | Purpose |
|---|---|---|
| `Button`, `ButtonWithTooltip` | `ui/button/` | Standard button and a button with an attached tooltip |
| `Txt` | `ui/Txt.svelte` | Typography primitive with a semantic variant prop |
| `Dialog`, `ConfirmDialog`, `InfoDialog` | `ui/dialog/` | Modal dialogs — generic, confirm-action, and informational variants |
| `DropdownMenu` + items | `ui/dropdown-menu/` | Full dropdown composition: groups, separators, checkbox/radio/switch items, detail view |
| `Popover`, `InfoPopover` | `ui/popover/` | Floating popover and a pre-styled info variant |
| `SingleSelect` | `ui/select/` | Styled single-value select input |
| `BottomSheet` | `ui/sheet/` | Mobile-friendly bottom drawer |
| `Slider` | `ui/slider/` | Range input slider |
| `Switch` | `ui/switch/` | Toggle switch |
| `Tabs` | `ui/tabs/` | Tab navigation |
| `Tooltip` | `ui/tooltip/` | Floating tooltip |
| `Toaster` + `ToastContext` | `ui/toast/` | Toast notification system. Use `ToastContext` to push toasts from any component. |
| `Badge` | `ui/badge/` | Label/badge chip |
| `RadialProgress` | `ui/radial-progress/` | Circular progress indicator |
| `BorderBeam` | `ui/border-beam/` | Animated border highlight effect |
| `StatusDot` | `ui/status-dot/` | Colored status indicator dot |
| `Separator` | `ui/separator/` | Visual divider line |

---

## Utility Components (`components/util/`)

These are composable helpers that make building complex components easier. They have no business logic and no dependency on app state.

| Component | Purpose |
|---|---|
| `Link.svelte` | Accessible anchor wrapper — see below |
| `SnippetOrString.svelte` / `SnippetOrStringTrigger.svelte` | Renders a prop that is either a plain string or a Svelte Snippet — see below |
| `Breakpoint.svelte` / `breakpoints.ts` | Reactive breakpoint detection. Exposes the current breakpoint as a Svelte reactive value so components can respond to viewport changes in script code, not just CSS media queries. |

### Link — accessible anchor primitive

`components/util/link/Link.svelte` is the standard anchor component. Always use it instead of a bare `<a>` tag when you need:

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

| Prop | Type | Default | Description |
|---|---|---|---|
| `href` | `string` | `''` | Navigation target. Set to `javascript:void(0)` when empty or `disabled`. |
| `target` | `string` | `''` | Standard anchor `target`. |
| `rel` | `string` | `''` | Overrides the automatic `rel`. Defaults to `noopener noreferrer` when `target="_blank"` and `rel` is not set. |
| `disabled` | `boolean` | `false` | Prevents navigation and applies a `disabled` class. |
| `children` | `Snippet` | — | Link content. |

All other `HTMLAnchorAttributes` (`class`, `aria-*`, `data-*`, …) are forwarded via rest-props. `href`, `rel`, and `onclick` are computed with `$derived.by()` so they react to `disabled` and `target` changes. Attributes with no value (empty `target`, empty `rel`, no `onclick`) are omitted from the rendered `<a>` to keep the HTML clean.

### `SnippetOrString` — polymorphic content props

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

When the same pattern appears in multiple components, use `components/util/snippetOrString/SnippetOrString.svelte` to avoid repetition. The utility is generic to accept typed snippet arguments:

```svelte
<!-- components/util/snippetOrString/SnippetOrString.svelte -->
<script lang="ts" generics="T">
    import type {Snippet} from 'svelte';
    interface Props { value: string | Snippet<[T | undefined]>; snippetArgs?: T; }
    const {value, snippetArgs}: Props = $props();
</script>
{#if typeof value === 'string'}{value}{:else}{@render value(snippetArgs)}{/if}
```

`SnippetOrStringTrigger.svelte` is a companion component for cases where the snippet renders a trigger element (e.g. inside a dropdown or popover) and needs to receive slot-like content from the parent.

---

## Component Documentation

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

---

## Resolving Conflicting Attribute Types

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

---

## Unique ID Generation

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

---

## Component Organisation

- **`snippets/`** — top-level entry points, one per embedded page slot. Keep them thin: pull state from stores and delegate rendering to components.
- **`components/`** — reusable building blocks used by multiple snippets. A component should have no knowledge of which snippet uses it.
- **`components/ui/`** — low-level primitive components (buttons, inputs, links, chips, …) with no business logic and no dependency on app state or domain types. Modelled after the shadcn/ui pattern: each file is a single focused primitive that higher-level components in `components/` compose. Snippets import from `components/`, not directly from `ui/`, unless the usage is trivially simple.
- **`stores/`** — all reactive state that crosses component boundaries. Components read from and write to stores; they do not pass callbacks between siblings.

---

## `mergeProps` — Prop Merging

`mergeProps` (from `bits-ui`) is the standard way to forward rest-props onto a root element while keeping component-owned defaults. It accepts up to 6 objects and applies these merge rules:

| Key type | Merge behaviour |
|---|---|
| `on*` event handlers | Both handlers are called in sequence — neither is overwritten |
| `class` | Accumulated into an array; falsy entries filtered out |
| Everything else | Last value wins (standard overwrite) |

```svelte
<script lang="ts">
    import {mergeProps} from 'bits-ui';
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

---

## `$bindable()` — Two-Way Binding

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

---

## Context — Parent-Child Communication

HAWKI uses plain Svelte `setContext`/`getContext` wrapped in typed factory functions. The context class and its factory functions live in a dedicated `*.svelte.ts` file — the `.svelte.ts` extension is required when the class uses Svelte runes; a plain `.ts` extension works for classes without runes.

**Pattern:**

- Define a class that owns the shared state and behaviour.
- Export a `setContextXX()` or `createContextXX()` function that instantiates the class, registers it via `setContext`, and returns the instance. Call this in the parent component.
- Export a `useContextXX()` function that retrieves the instance via `getContext` and throws with a clear message if not found. Call this in child components.
- Use a module-scoped `Symbol` as the context key to avoid collisions.

```ts
// ToolMenuFocusContext.svelte.ts
import {getContext, setContext} from 'svelte';

export class ToolMenuFocusContext {
    // ... state and methods
}

const toolMenuFocusContextKey = Symbol('toolMenuFocus');

export function setToolMenuFocusContext(): ToolMenuFocusContext {
    const context = new ToolMenuFocusContext();
    setContext(toolMenuFocusContextKey, context);
    return context;
}

export function useToolMenuFocusContext(): ToolMenuFocusContext {
    const context = getContext<ToolMenuFocusContext>(toolMenuFocusContextKey);
    if (!context) {
        throw new Error('useToolMenuFocusContext has no access to ToolMenuFocusContext.');
    }
    return context;
}
```

Use `setContextXX` when the factory only creates and registers. Use `createContextXX` when setup is heavier — for example subscribing to stores or wiring multiple objects together. See `resources/js/components/chat/composer/contexts/ComposerContext.svelte.ts` for an example of the heavier pattern.

> **Note:** The project does not use the `runed` package for context management. Do not introduce `Context` from `runed` for new code.

