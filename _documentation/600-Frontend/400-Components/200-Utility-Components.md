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
