<!--
  @component Shared page header bar: the semantic <header> for a page's fixed
  top bar — a space-between flex row sized to the nav toggle, with spacing and a
  reserve for that toggle, over the blurred fading backdrop that
  dissolves content scrolling underneath. Claims the Page shell's header row
  via grid-area (auto-placed in grids without that named area).

  Three shapes: custom content via children (the chat header's name and
  menus), a plain `heading` (the shell's default title row), or a `spacer`
  that keeps the row reserved while a page loads. Bars whose visible content
  carries no heading can declare one for the document outline via
  `srHeading`, rendered as a visually hidden h1.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLElement> {
        /** Standard bar heading; renders the default single-line title row. */
        heading?: string;
        /** Visually hidden h1 for the document outline — for bars whose
         *  visible content is not a heading (e.g. the chat header's name
         *  menu). Never combine with `heading`, that would duplicate h1s. */
        srHeading?: string;
        /** A full-bleed cover runs behind the bar (the assistant detail):
         *  the bar drops its backdrop and floats transparent above portaled
         *  overlays, letting clicks pass through its empty areas — content
         *  that must stay clickable re-enables pointer-events on its own
         *  root. */
        overCover?: boolean;
        /** Bar content: menus, actions — when no `heading` is given. */
        children?: Snippet;
        /** Empty spacer reserving the header row while a page loads. */
        spacer?: boolean;
    }

    const {heading, srHeading, overCover = false, children, spacer = false, class: className, ...rest}: Props = $props();
</script>

<header
    {...rest}
    class={['page-header-bar', overCover && 'over-cover', spacer && 'spacer', className]}
    aria-hidden={spacer ? 'true' : undefined}
>
    {#if srHeading}
        <h1 class="u-sr-only">{srHeading}</h1>
    {/if}
    {#if spacer}
        <!-- Nothing rendered: the row stays reserved until the real bar loads. -->
    {:else if heading}
        <h1 class="page-title">{heading}</h1>
    {:else}
        {@render children?.()}
    {/if}
</header>

<style>
    .page-header-bar {
        position: relative;
        /* Own stacking context, so the backdrop pseudo's negative z-index
            stays behind this bar's content but above whatever the owning page
            paints below — independent of the page's own stacking strategy. */
        isolation: isolate;
        /* Above everything the page body paints, positioned cards included —
           lets pages keep this bar FIRST in the document for reading and tab
           order instead of winning the paint by document order. */
        z-index: 1;
        /* Claims the Page shell's header row; grids without that named area
           auto-place the bar instead. */
        grid-area: header;
        display: flex;
        /* 2 × toggle inset + toggle height, so the row centres on the nav toggle. */
        min-height: calc(2 * var(--space-4) + var(--nav-row-h));
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
        /* The left side reserves room beside the floating nav toggle. */
        padding: var(--space-2) var(--space-5) var(--space-2) calc(var(--space-4) + var(--nav-row-h) + var(--space-2));
    }

    /* Soft fade instead of a hard divider: content scrolls under the bar and
       dissolves. Blur and tint ride one smootherstep mask that is solid only
       behind the bar row and reaches zero a short way below it. */
    .page-header-bar::before {
        content: '';
        position: absolute;
        /* Behind the bar's content (see isolation above), above the page. */
        z-index: -1;
        inset: 0 0 calc(-1 * var(--space-6));
        pointer-events: none;
        background: color-mix(in oklch, var(--panel-bg) 92%, transparent);
        backdrop-filter: blur(8px);
        --header-fade: linear-gradient(
            to bottom,
            black 0%,
            black 50%,
            rgba(0, 0, 0, 0.97) 55%,
            rgba(0, 0, 0, 0.89) 60%,
            rgba(0, 0, 0, 0.74) 65%,
            rgba(0, 0, 0, 0.55) 70%,
            rgba(0, 0, 0, 0.35) 75%,
            rgba(0, 0, 0, 0.19) 80%,
            rgba(0, 0, 0, 0.08) 85%,
            rgba(0, 0, 0, 0.02) 90%,
            transparent 95%
        );
        mask-image: var(--header-fade);
        -webkit-mask-image: var(--header-fade);
    }

    /* Loading spacer: no content, no backdrop — just the reserved row with a
       divider edge until the real bar's data has loaded. */
    .page-header-bar.spacer {
        border-bottom: var(--divider);
    }

    .page-header-bar.spacer::before {
        display: none;
    }

    /* Over a full-bleed cover: no backdrop (the cover's colour is the
       background), and above portaled overlays (the detail modal, z-index 2)
       so the bar's own rows stay reachable. The bar's box lets clicks pass
       through its empty areas to the cover beneath — content that must stay
       clickable re-enables pointer-events on its own root (the trail does;
       a snippet's roots cannot be matched from here). */
    .page-header-bar.over-cover {
        z-index: 3;
        pointer-events: none;
    }

    .page-header-bar.over-cover::before {
        display: none;
    }

    /* Single-line title. */
    .page-title {
        margin: 0;
        min-width: 0;
        font-size: var(--font-size-lg);
        line-height: var(--line-height-tight);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }

    @media (--bp-md-and-smaller) {
        .page-header-bar {
            padding-right: var(--space-3);
        }

        .page-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
</style>
