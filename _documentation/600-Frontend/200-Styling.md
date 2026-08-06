# Styling

HAWKI uses a CSS cascade layer system combined with a design token library. This document explains the architecture, available tokens, breakpoints, and the patterns for writing component styles.

---

## Architecture

The project uses a **CSS cascade layer system** to give explicit control over specificity. Layers are declared once in `resources/css/app.css`:

```
@layer reset, tokens, base, components, utilities;
```

Priority (lowest → highest): `reset` < `tokens` < `base` < `components` < `utilities`. This eliminates all need for `!important` — specificity is explicit and intentional.

All design values — colors, spacing, typography, radii, shadows, transitions — are defined as CSS custom properties in `resources/css/tokens/`. Svelte scoped `<style>` blocks compile into the `components` layer automatically.

```
resources/css/
├── app.css                   entry point: @layer declaration + imports
├── tokens/
│   ├── borders.css           border-related tokens
│   ├── breakpoints.css       custom media query definitions
│   ├── colors.css            OKLCH color scales + semantic aliases
│   ├── typography.css        font sizes, weights, line heights
│   ├── spacing.css           --space-1 through --space-16
│   ├── radius.css            --corner-sm / md / lg / full
│   ├── shadows.css           --elevation-none / 1 / 2
│   └── transitions.css       --duration-* and --easing-*
└── layers/
    ├── reset.css             minimal modern reset
    └── base.css              body, focus ring, scrollbar defaults
```

Dark mode is toggled via `[data-theme="dark"]` on `<html>`, with `@media (prefers-color-scheme: dark)` as an OS-level fallback. All color tokens update automatically — **components need no dark-mode-specific rules of their own**.

---

## Token Reference

All tokens are available as CSS custom properties on every element. Common groups:

| Group       | Example tokens                                                                                                 |
|-------------|----------------------------------------------------------------------------------------------------------------|
| Colors      | `--color-bg`, `--color-surface`, `--color-text`, `--color-text-muted`, `--color-interactive`, `--color-border` |
| Typography  | `--font-size-xs` → `--font-size-2xl`, `--font-weight-medium`, `--line-height-normal`                           |
| Spacing     | `--space-1` (4px) → `--space-16` (64px)                                                                        |
| Radius      | `--corner-sm` (5px), `--corner-md` (10px), `--corner-lg` (30px), `--corner-full`                               |
| Shadows     | `--elevation-none`, `--elevation-1`, `--elevation-2`                                                           |
| Transitions | `--duration-fast` (300ms), `--duration-normal`, `--easing-default`, `--easing-spring`                          |

The full list of available tokens lives in the individual files under `resources/css/tokens/`.

---

## Breakpoints

Breakpoints are defined as [CSS Custom Media Queries](https://www.w3.org/TR/mediaqueries-5/#custom-mq) in `resources/css/tokens/breakpoints.css` and processed by [`postcss-custom-media`](https://github.com/csstools/postcss-plugins/tree/main/plugins/postcss-custom-media). They are made globally available across all CSS files (including Svelte `<style>` blocks) via `@csstools/postcss-global-data`.

| Range | Min    | Max    |
|-------|--------|--------|
| `xxs` | 0      | 300px  |
| `xs`  | 0      | 549px  |
| `sm`  | 550px  | 767px  |
| `md`  | 768px  | 991px  |
| `lg`  | 992px  | 1199px |
| `xl`  | 1200px | —      |

Each range exposes several named queries:

| Query                       | Matches                          |
|-----------------------------|----------------------------------|
| `--bp-{range}`              | Exactly that range               |
| `--bp-{range}-and-smaller`  | That range and below             |
| `--bp-{range}-and-bigger`   | That range and above             |
| `--bp-smaller-than-{range}` | Everything below the range's min |
| `--bp-bigger-than-{range}`  | Everything above the range's max |
| `--bp-mode-mobile`          | `max-width: 850px`               |
| `--bp-mode-desktop`         | `min-width: 851px`               |

```css
/* In any .svelte <style> block or .css file */
@media (--bp-md-and-bigger) {
    .sidebar {
        display: flex;
    }
}

@media (--bp-sm-and-smaller) {
    .nav {
        flex-direction: column;
    }
}
```

PostCSS expands these to standard `@media` queries at build time — no browser support concerns.

---

## Writing Component Styles

Write all component styles in the `<style>` block of the `.svelte` file. Svelte scopes them automatically — no BEM class naming is needed to prevent leakage between components.

### Token Usage Levels

There are two levels of token use inside a component:

1. **Reference globals directly** for properties that are set once and never vary across states: `border-radius: var(--corner-md)`, `padding: var(--space-6)`.
2. **Declare a component-local token** at the root element of the component (the outermost DOM element — *not* CSS `:root`) for any value that either appears in multiple places *or* changes under a state rule. Reassigning the local token in a state rule then propagates the change to every property referencing it automatically, so each state override collapses to the minimum number of lines.

If you want the component to be externally restylable (e.g. a reusable primitive), the fallback form `var(--card-elevation, var(--elevation-1))` lets a parent pass `--card-elevation` to customise without needing to pierce Svelte's scope.

### State Rules

State rules (`:hover`, `:focus`, `[disabled]`, etc.) should reassign component-local tokens only — never repeat property declarations. The browser re-evaluates every property referencing the token automatically, so each state collapses to the minimum number of lines.

### Full Example

```svelte
<!-- resources/js/components/Card.svelte -->
<script lang="ts">
    interface Props {
        title: string;
        children?: import('svelte').Snippet;
    }
    const { title, children }: Props = $props();
</script>

<div class="card">
    <h2 class="card__title">{title}</h2>
    {#if children}
        <div class="card__body">{@render children()}</div>
    {/if}
</div>

<style>
    /*
     * Declare a component-local token at the root element of the component
     * (the outermost DOM element, not CSS :root) when the value either:
     *   - appears in multiple properties, or
     *   - needs to change under a state rule (:hover, :focus, [disabled], …)
     * For single-use, never-changing values, reference the global token directly.
     */
    .card {
        --card-bg:        var(--color-surface);
        --card-border:    var(--color-border);
        --card-elevation: var(--elevation-1);

        background:    var(--card-bg);
        border:        1px solid var(--card-border);
        border-radius: var(--corner-md);         /* single-use — global token directly */
        box-shadow:    var(--card-elevation);
        padding:       var(--space-6);            /* single-use — global token directly */
        transition:    box-shadow var(--duration-fast) var(--easing-default);
    }

    /*
     * State rules reassign local tokens only — never repeat property declarations.
     * The browser re-evaluates every property referencing the token automatically,
     * so each state collapses to the minimum number of lines.
     */
    .card:hover {
        --card-border:    var(--color-border-strong);
        --card-elevation: var(--elevation-2);
    }

    .card__title {
        font-size:     var(--font-size-lg);
        font-weight:   var(--font-weight-bold);
        color:         var(--color-text);
        margin-bottom: var(--space-3);
    }

    .card__body {
        color:     var(--color-text-muted);
        font-size: var(--font-size-sm);
    }
</style>
```

Because color tokens automatically switch values under `[data-theme="dark"]`, this component works correctly in both themes with no additional CSS.

---

## Variant Components (CVA)

When a component exposes props that drive visual style (`size`, `intent`, `weight`, …), use `cva` from `class-variance-authority` instead of manually constructing class strings. This keeps the variant→class mapping declarative and gives you type-safe prop types for free:

```svelte
<script lang="ts">
    import {cva, type VariantProps} from 'class-variance-authority';
    import {mergeProps} from 'bits-ui';
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

`VariantProps<typeof buttonVariants>` automatically reflects the valid values from the definition — no manual union types needed. `defaultVariants` eliminates `?? 'fallback'` chains. Use `cx` (re-exported from `class-variance-authority`) directly when you need ad-hoc class merging without full variant definitions.

---

## Adding Global Styles

When a style rule doesn't belong inside a Svelte `<style>` block — for example, a modifier class applied by the legacy Blade layer to affect Svelte component rendering — add it in `resources/css/layers/`. Create a new `.css` file there and import it in `resources/css/app.css` under the appropriate `@layer` declaration.

Do not use `:global()` in Svelte components for rules that apply across component boundaries. A `layers/` file is the correct home for cross-boundary rules.

---

## Z-Index and Stacking

**Avoid ad-hoc `z-index`.** In a component ecosystem where you cannot predict how components nest into each other, hand-rolled stacking values are brittle — they break down as soon as two popovers or tooltips are open at the same time, or a modal must open from inside a tooltip while the popover behind it stays visible. A global token ladder (`--z-popover: 600`, `--z-modal: 800`, …) looks tidy until components combine in ways the ladder didn't anticipate, at which point every fix requires raising numbers and introducing exceptions.

**The real fix for overlay components is portals.** Components like `Dialog`, `BottomSheet`, and `Popover` teleport their DOM to a root-level container (typically `<body>`), placing the rendered node completely outside any ancestor stacking context that would otherwise trap it. This is why simply moving a component tag to the end of the Svelte template is often enough — the teleported node lands at the end of the portal target, not because of local sibling order, but because it escapes the subtree entirely.

```svelte
<!-- ✗ Don't roll your own overlay with position: fixed + z-index -->
<div style="position: fixed; z-index: 600">…</div>

<!-- ✓ Use a component that portals its DOM — stacking context is no longer your problem -->
<Dialog bind:open={dialogOpen} />
```

Note that portaled components may carry a `z-index` value internally — that is the library's concern, not yours. What you must avoid is adding `z-index` in your own component code to patch a stacking problem.

**DOM ordering is a secondary technique**, valid only when elements share the same stacking context and no ancestor breaks it. Many CSS properties create a new stacking context (`transform`, `opacity < 1`, `filter`, `isolation: isolate`, positioned `z-index`) — once a new stacking context exists, later siblings outside it cannot paint over elements inside it regardless of order. Do not rely on DOM ordering alone for overlay elements.

Only introduce `z-index` as a genuine last resort, and only with a component-local token so the value is visible and scoped:

```css
.my-component {
    --my-component-z: 1;  /* document why 1 and what it sits above */
    z-index: var(--my-component-z);
}
```

Never use bare numeric `z-index` values or a global `--z-*` token ladder.

---

## Rules

- **No `!important`** — ever. Cascade layers make it unnecessary.
- **No hardcoded colors** — always reference a token. If no suitable token exists, add one to `resources/css/tokens/colors.css`.
- **No hardcoded sizes** — use spacing, radius, or typography tokens.
- **States reassign component-local tokens**, not global ones. Because the browser re-evaluates every property referencing the token automatically, one reassignment line replaces what would otherwise be repeated property declarations in every state rule.
- **No utility-class spam** — if a pattern repeats across 3+ components, extract a shared Svelte primitive, not a utility class.
- **Dark mode is free** — do not add `[data-theme="dark"]` rules inside component styles. The token layer handles it globally.
- **No ad-hoc `z-index`** — use portaled overlay components instead of rolling `position: fixed` + `z-index`. DOM ordering is only a secondary aid within a shared stacking context. See [Z-Index and Stacking](#z-index-and-stacking) for the full rationale and the allowed exception form.
- **Do not add new rules to `public/css/`** — those files belong to the legacy layer and are being phased out. New styles must use the token system described above.
