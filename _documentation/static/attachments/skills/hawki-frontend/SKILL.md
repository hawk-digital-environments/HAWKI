---
name: hawki-frontend
description: HAWKI frontend coding standards for Svelte 5 + TypeScript. Use when writing or reviewing frontend code for HAWKI, creating Svelte components, snippets, stores, or when asked about frontend architecture.
---

> **Note:** The current codebase may not fully follow these guidelines. Follow these rules in all new code and refactor toward them when possible. **Do not add new code to the legacy vanilla-JS layer (`public/js/`).** All new frontend work must follow the patterns described here.

## Technology Stack

- **Svelte 5** with Runes API (`$state`, `$derived`, `$props`, …) — no Options API / legacy Svelte 4 syntax
- **TypeScript** — every `.svelte` and `.ts` file must be typed; avoid `any`
- **Vite** bundler (`vite.config.js` / `svelte.config.js`)
- **`class-variance-authority` (CVA)** — declarative variant→class mapping for components with style-driving props (`size`, `intent`, …); `cx` re-exported from CVA is used internally by `mergeProps` for class merging

## Directory Structure

```
resources/js/
├── svelte/
│   ├── components/          ← Reusable general-purpose Svelte components
│   ├── snippets/            ← Blade-embeddable entry points (one per page slot)
│   ├── stores/              ← Svelte 5 reactive store classes (*.svelte.ts)
│   ├── types/
│   │   ├── ai.ts            ← AiModelResource, SystemModelResource, SystemPromptResource, labels
│   │   ├── connection.ts    ← InternalConnectionConfig, route types
│   │   └── translation.ts  ← Locale, LocaleCode, LocaleRecord
│   └── svelteSnippetLoader.ts ← Custom-element bridge for Blade integration
└── util/
    ├── hawkiConnection.ts   ← Server-rendered connection data accessor
    ├── translator.ts        ← Client-side translation helper
    ├── mergeProps.ts        ← Merges prop objects; uses cx from CVA for class merging
    └── fileIconSvg.ts       ← File-type icon helper
```

## Hybrid Approach: Snippets

HAWKI embeds Svelte into Blade via **Snippets** — self-contained components mounted in server-rendered pages. Transitional architecture toward a full SPA; snippets become SPA building blocks.

### Embedding with `<x-svelte>`

```blade
{{-- Minimal --}}
<x-svelte type="ChatInput" />

{{-- With PHP props and extra HTML attributes --}}
<x-svelte type="ChatInput" :props="['readonly' => true]" class="my-class" />
```

`type` = filename inside `resources/js/svelte/snippets/` without `.svelte`. Props are JSON-encoded automatically. Extra HTML attributes are forwarded verbatim.

**Auto-discovery:** Vite's `import.meta.glob` discovers all snippets at build time — no manual imports or registrations needed when adding a new snippet.

**Lifecycle:** mounted when element enters the DOM, destroyed when it leaves, destroyed + remounted when `type` or `props` change. Treat snippets as stateless from the outside — internal state resets on every remount.

### Adding a Snippet

1. Create `resources/js/svelte/snippets/MyWidget.svelte`
2. Use in Blade: `<x-svelte type="MyWidget" />`

Nothing else needed.

### The `root` Prop

Every snippet automatically receives `root` — a reference to the `<svelte-snippet>` DOM element. Use it to read additional HTML attributes or dispatch custom events to legacy vanilla-JS:

```svelte
<script lang="ts">
    import type {HTMLSvelteSnippetElement} from '../svelteSnippetLoader.js';
    interface Props { root: HTMLSvelteSnippetElement; }
    const {root}: Props = $props();

    function notifyLegacy(value: string) {
        root.dispatchEvent(new CustomEvent('myWidget:change', {detail: {value}, bubbles: true}));
    }
</script>
```

## Accessing Server Data

The backend injects a JSON blob into the page. Access it via `hawkiConnection`:

```ts
import {hawkiConnection} from '../../util/hawkiConnection.js';

const config = hawkiConnection();                                       // full config
const aiConfig = hawkiConnection('ai');                                 // top-level key
const mimeTypes = hawkiConnection('storage.allowedMimeTypes') as string[]; // dot-notation
```

Return type derived from `InternalConnectionConfig` in `resources/js/svelte/types/connection.ts`. Add new fields there when the backend exposes new data.

## Translations

```ts
import {translate} from '../../util/translator.ts';

translate('chat.send_button');
translate('errors.file_too_large', {size: '10 MB'});
translate('room.invite', {name: (inner) => `<strong>${inner}</strong>`});
```

Mirrors Laravel's `Translator::makeReplacements()`: supports `:placeholder`, `:Placeholder`, `:PLACEHOLDER` casing variants and tag-callback replacements. Keys sourced from `translation.labels` in the connection blob (from `resources/language/*.json`).

## Reactive Stores

Shared reactive state lives in `resources/js/svelte/stores/` as TypeScript classes using Svelte 5 Runes. File extension: `.svelte.ts` (required for the Svelte compiler to process runes).

Each store file exports the class and a pre-constructed singleton:

```ts
// resources/js/svelte/stores/MyStore.svelte.ts
export class MyStore {
    public count = $state(0);
    public doubled = $derived(this.count * 2);
}
export const myStore = new MyStore();
```

```svelte
<script lang="ts">
    import {myStore} from '../stores/MyStore.svelte.js';
</script>
<p>Count: {myStore.count}</p>
```

> Use `.js` extension in imports — Vite resolves `.svelte.ts` files when a `.js` extension is used (standard TypeScript ESM convention).

## Types

Extend shared types in `resources/js/svelte/types/` rather than defining one-off local interfaces in component files.

| File | Contents |
|---|---|
| `ai.ts` | `AiModelResource`, `SystemModelResource`, `SystemPromptResource`, capability/tool labels |
| `connection.ts` | `InternalConnectionConfig`, `CommonConnectionConfig`, route types |
| `translation.ts` | `Locale`, `LocaleCode`, `LocaleRecord` |

## Component Organisation

| Directory | Role |
|---|---|
| `snippets/` | Blade entry points — thin shells; pull state from stores, delegate rendering to components |
| `components/` | Reusable building blocks — no knowledge of which snippet uses them |
| `stores/` | All reactive state that crosses component boundaries |

Components read from and write to stores; they do not pass callbacks between siblings.

## Component Documentation

Every Svelte component requires a `@component` block comment immediately before `<script>`:

```svelte
<!--
  @component General description of what this component does and when to use it.
-->
<script lang="ts">
```

All props must be documented with JSDoc inside the `Props` interface. `Props` must extend the appropriate `HTMLAttributes` type so standard HTML attributes are accepted without redeclaration:

```svelte
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Description of this prop. */
        requiredProp: string;
        /**
         * Description.
         * @deprecated — use `requiredProp` instead.
         */
        optionalProp?: string;
    }

    const { requiredProp, optionalProp, ...rest }: Props = $props();
</script>
```

When a prop conflicts with an HTML attribute signature, widen the conflict to `any` in an intermediate interface:

```svelte
interface NonConflictingProps extends HTMLAttributes<HTMLDivElement> {
    onchange?: any;
}
interface Props extends NonConflictingProps {
    /** @param newValue The newly selected value. */
    onchange?: (newValue: string) => void;
}
```

## Styling

### Architecture

CSS cascade layers declared in `resources/css/app.css` (lowest → highest priority):

```
@layer reset, tokens, base, components, utilities;
```

Svelte scoped `<style>` blocks compile into the `components` layer — no `!important` ever needed. Design tokens live in `resources/css/tokens/`:

| File | Contents |
|---|---|
| `colors.css` | OKLCH color scales + semantic aliases |
| `typography.css` | font sizes, weights, line heights |
| `spacing.css` | `--space-1` (4px) → `--space-16` (64px) |
| `radius.css` | `--corner-sm/md/lg/full` |
| `shadows.css` | `--elevation-none/1/2` |
| `transitions.css` | `--duration-*` and `--easing-*` |

Dark mode via `[data-theme="dark"]` on `<html>` — all color tokens update automatically. **Components need zero dark-mode-specific rules.**

### Token Quick Reference

| Group | Tokens |
|---|---|
| Colors | `--color-bg`, `--color-surface`, `--color-text`, `--color-text-muted`, `--color-interactive`, `--color-border` |
| Typography | `--font-size-xs` → `--font-size-2xl`, `--font-weight-medium`, `--line-height-normal` |
| Spacing | `--space-1` (4px) → `--space-16` (64px) |
| Radius | `--corner-sm` (5px), `--corner-md` (10px), `--corner-lg` (30px), `--corner-full` |
| Shadows | `--elevation-none`, `--elevation-1`, `--elevation-2` |
| Transitions | `--duration-fast` (300ms), `--duration-normal`, `--easing-default`, `--easing-spring` |

### Breakpoints

Breakpoints are defined as CSS Custom Media Queries in `resources/css/tokens/breakpoints.css` and processed by `postcss-custom-media`. They are globally available in all CSS files including Svelte `<style>` blocks.

| Range | Min | Max |
|---|---|---|
| `xxs` | 0 | 300px |
| `xs` | 0 | 549px |
| `sm` | 550px | 767px |
| `md` | 768px | 991px |
| `lg` | 992px | 1199px |
| `xl` | 1200px | — |

Each range exposes named queries:

| Query | Matches |
|---|---|
| `--bp-{range}` | Exactly that range |
| `--bp-{range}-and-smaller` | That range and below |
| `--bp-{range}-and-bigger` | That range and above |
| `--bp-smaller-than-{range}` | Everything below the range's min |
| `--bp-bigger-than-{range}` | Everything above the range's max |
| `--bp-mode-mobile` | `max-width: 850px` |
| `--bp-mode-desktop` | `min-width: 851px` |

```css
/* In any .svelte <style> block or .css file */
@media (--bp-md-and-bigger) { .sidebar { display: flex; } }
@media (--bp-sm-and-smaller) { .nav { flex-direction: column; } }
```

PostCSS expands these to standard `@media` queries at build time.

### Writing Component Styles

Write all styles in the `.svelte` `<style>` block. Two patterns:

1. **Reference globals directly** for single-use, never-changing values: `border-radius: var(--corner-md)`.
2. **Declare a component-local token** at the root element when a value appears in multiple places *or* changes under a state rule. State rules then reassign only the local token — the browser re-evaluates every property referencing it automatically:

```svelte
<style>
    .card {
        --card-bg:        var(--color-surface);
        --card-border:    var(--color-border);
        --card-elevation: var(--elevation-1);

        background:    var(--card-bg);
        border:        1px solid var(--card-border);
        border-radius: var(--corner-md);   /* single-use — global token directly */
        box-shadow:    var(--card-elevation);
        padding:       var(--space-6);      /* single-use — global token directly */
    }

    /* State: reassign local tokens only — never repeat property declarations */
    .card:hover {
        --card-border:    var(--color-border-strong);
        --card-elevation: var(--elevation-2);
    }
</style>
```

For external restylability (reusable primitives), use fallback form: `var(--card-elevation, var(--elevation-1))`.

### Variant Components (CVA)

When a component exposes props that drive visual style (`size`, `intent`, …), use `cva` from `class-variance-authority`. This keeps the variant→class mapping declarative and gives type-safe prop types for free:

```svelte
<script lang="ts">
    import {cva, type VariantProps} from 'class-variance-authority';
    import {mergeProps} from '../../util/mergeProps.js';
    import type {HTMLAttributes} from 'svelte/elements';

    const buttonVariants = cva('btn', {
        variants: {
            intent: {primary: 'btn--primary', secondary: 'btn--secondary'},
            size:   {sm: 'btn--sm', md: 'btn--md'},
        },
        defaultVariants: {intent: 'primary', size: 'md'},
    });

    interface Props extends HTMLAttributes<HTMLButtonElement> {
        intent?: VariantProps<typeof buttonVariants>['intent'];
        size?:   VariantProps<typeof buttonVariants>['size'];
    }

    const {intent, size, ...restProps}: Props = $props();

    const elementProps = $derived(mergeProps(
        {class: buttonVariants({intent, size})},
        restProps
    ));
</script>
```

`VariantProps<typeof buttonVariants>` automatically reflects valid values — no manual union types. `defaultVariants` eliminates `?? 'fallback'` chains. Use `cx` (re-exported from CVA) directly for ad-hoc class merging without full variant definitions.

### Rules

- **No `!important`** — cascade layers make it unnecessary
- **No hardcoded colors** — reference a token; if none exists, add to `resources/css/tokens/colors.css`
- **No hardcoded sizes** — use spacing/radius/typography tokens
- **States reassign component-local tokens**, not global ones
- **No utility-class spam** — if a pattern repeats across 3+ components, extract a shared Svelte primitive
- **Dark mode is free** — never add `[data-theme="dark"]` rules inside component styles
- **Legacy styles in `public/css/`** keep loading during SPA transition — do not add new rules to legacy files
