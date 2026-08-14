# Svelte Components

Conventions and patterns every HAWKI Svelte component must follow. These apply to all files under `resources/js/components/`, `resources/js/app/components/`, `resources/js/plugins/`, and any `.svelte` or `.svelte.ts` file you create.

---

## Component Documentation

Every Svelte component must carry a `@component` block comment immediately before the `<script>` tag. VS Code and similar tooling shows this text in hover tooltips:

```svelte
<!--
  @component General description of what this component does and when to use it.
-->
<script lang="ts">
```

All props must be documented with a JSDoc comment inside the `Props` interface. Mark deprecated props with `@deprecated` and include a migration hint.

`Props` must always extend the appropriate `HTMLAttributes` type from `svelte/elements` so that TypeScript accepts standard HTML attributes (`class`, `id`, `aria-*`, …) without explicit redeclaration:

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
         * @deprecated Use `requiredProp` instead.
         */
        optionalProp?: string;
    }

    const { requiredProp, optionalProp, ...rest }: Props = $props();
</script>
```

---

## `mergeProps` — Prop Forwarding

`mergeProps` (from `bits-ui`) is the standard way to forward rest-props onto a root element while keeping component-owned defaults. It accepts up to 6 objects and merges them as follows:

| Key type             | Merge behaviour                                               |
|----------------------|---------------------------------------------------------------|
| `on*` event handlers | Both handlers are called in sequence — neither is overwritten |
| `class`              | Accumulated into an array; falsy entries filtered out         |
| Everything else      | Last value wins (standard overwrite)                          |

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

Use `cx` (re-exported from `class-variance-authority`) when you only need ad-hoc class merging without a full prop-forward:

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

HAWKI uses Svelte's `createContext()` (Svelte ≥ 5.40) wrapped in typed factory functions. `createContext<T>()` returns a `[get, set]` tuple bound to one context type — there is no shared `Symbol` key to manage, and the type is fixed at the call site rather than re-stated on every `get`. The context class and its factory functions live in a dedicated `*.svelte.ts` file — the `.svelte.ts` extension is required when the class uses Svelte runes; a plain `.ts` extension works for classes without runes.

**Pattern:**

- Define a class that owns the shared state and behaviour.
- Create the typed context pair once at module scope: `const [get, set] = createContext<MyContext>();`
- Export a `create…Context()` function that constructs the instance and registers it (`set(context)`), returning the instance. Call this in the parent component.
- Export a `use…Context()` function that retrieves the instance (`get()`) and throws with a clear message if none was set. Call this in child components.

```ts
// ToolMenuFocusContext.svelte.ts
import {createContext} from 'svelte';

export class ToolMenuFocusContext {
    // ... state and methods
}

const [get, set] = createContext<ToolMenuFocusContext>();

export function createToolMenuFocusContext(): ToolMenuFocusContext {
    const context = new ToolMenuFocusContext();
    set(context);
    return context;
}

export function useToolMenuFocusContext(): ToolMenuFocusContext {
    const context = get();
    if (!context) {
        throw new Error('useToolMenuFocusContext has no access to ToolMenuFocusContext.');
    }
    return context;
}
```

`get()` throws when called outside a component init or with no matching ancestor — that is the "no context found" signal, not a thrown custom error of your own. Wrap the `get()` call in a `try`/`catch` when a missing context is an expected branch (e.g. `useApp()` falls back to a legacy global when no Svelte context is set).

### `provide…` vs `create…Context`

The factory that publishes a context is named for what it does:

- **`create…Context()`** — the parent **constructs** the instance and registers it. Use this when the parent owns the object (the typical case). See `ToastContext.svelte.ts`, `ComposerContext.svelte.ts`.
- **`provide…()`** — the parent **forwards a reference** to something it already has (or only pins a setting), without constructing it. The clearest example is `provideApp(app)`: the app was built by `app.ts`, the `Shell` just hands the existing instance to its subtree via `set(app)`. `provideDefaultRouterName(name)` is the same shape for a plain string — it pins which router name `useRouter()` resolves to without building anything.

```ts
// app/hooks/useApp.svelte.ts
const [get, set] = createContext<HawkiApp>();

export function provideApp(app: HawkiApp) {
    set(app);
}

export function useApp(): HawkiApp {
    let app;
    try { app = get(); } catch { /* no context */ }
    return app ?? getHawkiApp(); // legacy fallback
}
```

### Naming the `use…` accessor

The accessor is `use…Context()` by default (`useToastContext()`, `useComposerContext()`). When the retrieved value has a better-known name, drop the `Context` suffix — `useApp()` returns a `HawkiApp`, `useRouter()` returns a `RouterHandle`, not "AppContext" / "RouterContext". Match the name to what the caller gets.

### Heavier `create…` factories

When `create…Context()` does more than instantiate one object — subscribing to stores, wiring event handlers, registering cleanup via `onDestroy`, constructing a cluster of related objects — it stays `create…Context()` (the heavier work is what makes it a "create" rather than a "provide"). See `plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.ts` for the heavy pattern.

> The project does not use the `runed` package for context management. Do not introduce `Context` from `runed` for new code.

---

## Resolving Conflicting Attribute Types

Sometimes a component prop shares a name with an attribute already defined on the HTML element but with an incompatible signature — for example, overriding `onchange` to accept a domain-specific value instead of a raw `Event`. TypeScript will reject the override directly. Use an intermediate interface that widens the conflicting member to `any` first, then narrow it in `Props`:

```svelte
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';

    interface NonConflictingProps extends HTMLAttributes<HTMLDivElement> {
        onchange?: any; // widen to any so Props can redefine it safely
    }

    interface Props extends NonConflictingProps {
        /**
         * Executed when the selected value changes.
         * @param newValue The newly selected value.
         */
        onchange?: (newValue: string) => void;
    }

    const { onchange, ...rest }: Props = $props();
</script>
```

---

## Unique ID Generation

Components that need a stable `id` for accessibility (`<label for="...">`, `aria-describedby`) should generate one with `$props.id()` and fall back to any explicitly provided `id` prop:

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

`$props.id()` is stable across renders for the same component instance and guaranteed unique across all instances. Never use `Math.random()` or a module-level counter for this purpose.

---

## Component Organisation

| Directory | What belongs here |
|---|---|
| `app/components/` | App-wide shell and layout components (e.g. `Shell.svelte`). Tied to the app, not reusable. |
| `components/` | Reusable building blocks used by multiple features. No knowledge of which feature uses them. |
| `components/ui/` | Low-level primitive components (buttons, inputs, links, chips, …) with no business logic and no dependency on app state or domain types. |
| `components/util/` | Composable utility components (`Link`, `Markdown`, `Breakpoint`, `SnippetOrString`). |
| `plugins/<name>/modules/<feature>/` | A feature's components, pages, and sub-components. Feature-local. |

:::info[Components library extraction]
The `components/` directory is slated to be extracted into a dedicated npm package so extension authors can reuse the same primitives. Keep `components/ui/` free of app-state and domain-type dependencies so the extraction stays clean.
:::

A component that gets complex enough to need its own state, context, or sub-components should be extracted from its page into its own directory under the feature module.

## Accessing Server Data

Use the hooks in `app/hooks/` to reach server data: `useConfig()` for runtime configuration, `useConnection()` (and its narrowing variants `useAuthenticatedConnection` / `useConnectionWithUserInfo`) for auth-state-aware access, `useStore()` for shared reactive state, and `useRestApi()` for typed fetches. All are available after the `preparation` boot stage. See [Data Layer](130-Data-Layer.md) and [Stores](120-Stores.md).

Use `useTranslator()`'s `__()` for all user-facing strings. See [Translations](140-Translations.md).
