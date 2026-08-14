# Utility Components

Composable helper components that make building complex components easier. They have no business logic and no dependency on app state. Use them to avoid reinventing common patterns.

---

## `Link` — Accessible Anchor

`components/util/link/Link.svelte` is the standard anchor component. Prefer it over a bare `<a>` tag whenever you need any of:

- Automatic `rel="noopener noreferrer"` on `target="_blank"` links (prevents tabnabbing)
- A `disabled` state that blocks navigation without removing the element from the DOM
- Router-driven links with an `active` class and `aria-current="page"` while they point at the current page

```svelte
<Link href="/dashboard">Dashboard</Link>

<!-- rel set automatically -->
<Link href="https://example.com" target="_blank">External link</Link>

<!-- navigation blocked, disabled class applied -->
<Link href="/action" disabled>Unavailable</Link>
```

`href` accepts either a plain string or a route object `{ name: string; params?: RouteParams }` for named-route navigation through the app router. Router-driven links get an `active` class and `aria-current="page"` while they point at the page currently shown. Use `activeMatch="prefix"` for a section link that should stay lit on nested pages:

```svelte
<Link href="/admin" activeMatch="prefix">Admin</Link>
```

The `active` class carries no styling of its own — set `--link-active-color` or `--link-active-font-weight` on an ancestor to give it a look.

For external links (different origin) the `children` snippet receives a `favicon` snippet (loaded through the backend proxy). Render it wherever it fits your layout, or ignore it for icon-less links:

```svelte
<Link href="https://example.com" target="_blank">
    {#snippet children({favicon})}
        <span class="header">{@render favicon()} example.com</span>
    {/snippet}
</Link>
```

For plain text links with the favicon prepended automatically, use `TextLink.svelte` instead.

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

`components/util/breakpoints/useBreakpoint.svelte.ts` exposes the current breakpoint as a Svelte reactive value, so components can respond to viewport changes in script code — not just CSS media queries.

`useBreakpoint()` returns a `{ is(breakpoint), matching() }` object:

```svelte
<script lang="ts">
    import {useBreakpoint} from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    const bp = useBreakpoint();
</script>

{#if bp.is('sm')}
    <BottomSheet>…</BottomSheet>
{:else if bp.matching().includes('md')}
    <Popover>…</Popover>
{/if}
```

`is(breakpoint)` returns `true` when the viewport matches the given breakpoint name (`'xxs'` | `'xs'` | `'sm'` | `'md'` | `'lg'` | `'xl'`). `matching()` returns the array of all breakpoints currently matching. There is no `isMobile` / `isDesktop` shorthand — check the range that fits your UI.

Use CSS media queries (via the `--bp-*` custom media tokens) for purely visual adjustments. Use `useBreakpoint` only when the branch affects component structure or behaviour that cannot be expressed in CSS alone.

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

Do not use `markstream-svelte`'s `MarkdownRender` directly — always use this wrapper so the worker setup and link extension are consistently applied.
