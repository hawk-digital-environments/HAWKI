# Utility Components

Composable helper components that make building complex components easier. They have no business logic and no dependency on app state. Use them to avoid reinventing common patterns.

---

## `Link` — Accessible Anchor

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

| Prop       | Type      | Default | Description                                                                                                   |
|------------|-----------|---------|---------------------------------------------------------------------------------------------------------------|
| `href`     | `string`  | `''`    | Navigation target. Set to `javascript:void(0)` when empty or disabled.                                        |
| `target`   | `string`  | `''`    | Standard anchor `target`.                                                                                     |
| `rel`      | `string`  | `''`    | Overrides the automatic `rel`. Defaults to `noopener noreferrer` when `target="_blank"` and `rel` is not set. |
| `disabled` | `boolean` | `false` | Prevents navigation and applies a `disabled` class.                                                           |
| `children` | `Snippet` | —       | Link content.                                                                                                 |

All other `HTMLAnchorAttributes` (`class`, `aria-*`, `data-*`, …) are forwarded via rest-props.

---

## `SnippetOrString` — Polymorphic Content Props

When a prop can be either a plain string or a rich Svelte Snippet (e.g. `label`, `description`, `error`), type it as `string | Snippet` and render both cases. For one-off use, write the branch inline:

```svelte
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        label?: string | Snippet;
    }
    const {label}: Props = $props();
</script>

{#if label}
    {#if typeof label === 'string'}
        <span>{label}</span>
    {:else}
        {@render label()}
    {/if}
{/if}
```

When the same pattern appears in multiple components, use `components/util/snippetOrString/SnippetOrString.svelte` to avoid repetition. The component is generic to accept typed snippet arguments:

```svelte
<!-- Usage -->
<SnippetOrString value={label} />

<!-- With typed snippet args -->
<SnippetOrString value={rowTemplate} snippetArgs={row} />
```

`SnippetOrStringTrigger.svelte` is a companion for cases where the snippet renders a trigger element inside a dropdown or popover.

---

## `Breakpoint` — Reactive Viewport Detection

`components/util/breakpoints/Breakpoint.svelte` and `breakpoints.ts` expose the current breakpoint as a Svelte reactive value, so components can respond to viewport changes in script code — not just CSS media queries.

```svelte
<script lang="ts">
    import {useBreakpoint} from '$lib/components/util/breakpoints/breakpoints.js';
    const bp = useBreakpoint();
</script>

{#if bp.isMobile}
    <BottomSheet>…</BottomSheet>
{:else}
    <Popover>…</Popover>
{/if}
```

Use CSS media queries (via the `--bp-*` custom media tokens) for purely visual adjustments. Use `Breakpoint` only when the branch affects component structure or behaviour that cannot be expressed in CSS alone.

---

## `Markdown` — Message Body Renderer

`components/util/markdown/Markdown.svelte` renders an AI message body. It wraps the `markstream-svelte` library and configures it with HAWKI-specific extensions:

- **KaTeX** and **Mermaid** are rendered in web workers to avoid blocking the main thread.
- **`ExtendedLinkNode`** replaces the built-in link renderer. It routes each link to the right primitive based on its target:
  - `#citation-…` anchors → `CitationReference` chip (scrolls to the matching citation tile)
  - Other `#…` hashes → smooth-scroll to the target element
  - External `http(s)` links → `TextLink` with favicon + `UrlPreviewTooltip` on hover
  - Same-origin and `mailto:` links → plain `TextLink`
  - Any other protocol (`javascript:`, etc.) → rendered as text only, no anchor
- **`MathNode`** wraps the built-in `MathBlockNode` for `math_block` nodes and adds a hover-reveal button that copies the raw LaTeX source (`node.raw`).

**Style overrides** — the component's `<style>` block also corrects `markstream-svelte` output to match HAWKI's design. All overrides are unlayered app CSS and therefore win over the package's `@layer components` rules without `!important`:

| Rule | Why |
|---|---|
| `li:has(> p > .checkbox-node) { list-style: none }` | Task items render GitHub-style: the checkbox replaces the list marker (the package shows both). |
| `input.checkbox-node { width/height/min-width/min-height: 0.8em; vertical-align: middle; accent-color }` | Checkboxes at native ~13px (0.8em at the 16px message font), middle-aligned, theme-tinted. The explicit **minimums are essential**: `public/css/style.css` gives every `input` `min-width: 10rem; min-height: 2.5rem` (text-field sizing) — and `min-*` clamps `width`/`height` regardless of importance, which rendered task checkboxes at 160×40px before the page rule was scoped with `:not([type="checkbox"]):not([type="radio"])`. |
| `[data-node-type="table"] { overflow-x: auto }` + `blockquote { overflow-x: auto }` | Wide tables scroll inside the message column. Top-level tables scroll via their node-slot wrapper; tables nested in a blockquote have no slot wrapper, so the blockquote is their scroll container. |
| `.html-preview-frame { width: 100%; height: 24rem; … }` | The HTML/SVG preview frame ships viewport-sized (80vw × 70vh) but renders inline — see `MARKSTREAM-SVELTE.md` (repo root) for the upstream stopgap details. |

Overrides marked as upstream stopgaps in `MARKSTREAM-SVELTE.md` are removed when the fixed package version is released and bumped.

```svelte
<script lang="ts">
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
</script>

<Markdown message={body} />

<!-- While the response is still streaming: -->
<Markdown message={partialBody} isStreaming={true} />
```

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `message` | `string` | — | The raw markdown string to render. |
| `isStreaming` | `boolean` | `false` | When `true`, enables the typewriter effect and keeps the renderer in incremental mode. |

Do not use `markstream-svelte`'s `MarkdownRender` directly — always use this wrapper so the worker setup and link extension are consistently applied.
