# Components

Conventions and patterns every HAWKI Svelte component must follow. These apply to all files under `resources/js/components/`, `resources/js/snippets/`, and any `.svelte` or `.svelte.ts` file you create.

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

| Directory        | What belongs here                                                                                                                            |
|------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| `snippets/`      | Top-level entry points, one per embedded page slot. Keep them thin: pull state from stores and delegate rendering to components.             |
| `components/`    | Reusable building blocks used by multiple snippets. A component should have no knowledge of which snippet uses it.                           |
| `components/ui/` | Low-level primitive components (buttons, inputs, links, chips, …) with no business logic and no dependency on app state or domain types.     |
| `stores/`        | All reactive state that crosses component boundaries. Components read from and write to stores; they do not pass callbacks between siblings. |

Snippets import from `components/`, not directly from `components/ui/`, unless the usage is trivially simple. A component that gets complex enough to need its own state, context, or sub-components should be extracted from its snippet into `components/`.

## Accessing Server Data

Use `getConfig()` for runtime configuration values and the `getConnection()` family for auth-state-aware access. Both are available after the `preparation` boot stage. See [Data Layer](../300-Data/index.md) for the full reference.

Use `__()` from `$lib/utils/translator.js` for all user-facing strings. See [Translations](../500-Utilities/100-Translations.md).

Shared reactive state lives in stores under `resources/js/stores/`. See [Stores](../300-Data/100-Stores.md).

Available UI primitives and utility components are listed under the [Components](/) section.
