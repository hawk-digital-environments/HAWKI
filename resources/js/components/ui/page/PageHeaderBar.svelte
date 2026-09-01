<!--
  @component Shared page header bar: the semantic <header> for a page's fixed
  top bar — a space-between flex row with the standard 3.75rem height, spacing
  and mobile nav-trigger reserve, over the blurred fading backdrop that
  dissolves content scrolling underneath. Claims the Page shell's header row
  via grid-area (auto-placed in grids without that named area).

  Three shapes: custom content via children (the chat header's name and
  menus), a plain `heading` (the shell's default title row), or a `spacer`
  that keeps the row reserved while a page loads.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLElement> {
        /** Standard bar heading; renders the default single-line title row. */
        heading?: string;
        /** Bar content: menus, actions — when no `heading` is given. */
        children?: Snippet;
        /** Empty spacer reserving the header row while a page loads. */
        spacer?: boolean;
    }

    const {heading, children, spacer = false, class: className, ...rest}: Props = $props();
</script>

<header
    {...rest}
    class={['page-header-bar', spacer && 'spacer', className]}
    aria-hidden={spacer ? 'true' : undefined}
>
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
        /* Claims the Page shell's header row; grids without that named area
           auto-place the bar instead. */
        grid-area: header;
        display: flex;
        min-height: 3.75rem;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
        padding: var(--space-2) var(--space-5);
    }

    /* Soft fade instead of a hard divider: the blurred panel backdrop is drawn
       on a pseudo-element that extends past the bar and fades out, so content
       scrolling underneath dissolves rather than hitting a line. */
    .page-header-bar::before {
        content: '';
        position: absolute;
        /* Behind the bar's content (see isolation above), above the page. */
        z-index: -1;
        inset: 0 0 -3rem;
        pointer-events: none;
        background: color-mix(in oklch, var(--panel-bg) 88%, transparent);
        backdrop-filter: blur(12px);
        /* Eased ramp (rather than one linear stop) so neither the start nor
           the end of the fade shows a visible edge. */
        --header-fade: linear-gradient(
            to bottom,
            black 0,
            black 45%,
            rgba(0, 0, 0, 0.86) 60%,
            rgba(0, 0, 0, 0.55) 72%,
            rgba(0, 0, 0, 0.25) 84%,
            rgba(0, 0, 0, 0.08) 92%,
            transparent 100%
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

    /* Single-line title. Its tight 1.5rem line centers inside the fixed
       3.75rem bar row exactly on the mobile nav toggle's centre
       (3.75rem = 2 × toggle inset + toggle height). */
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
            /* Reserves room beside the floating nav toggle. */
            padding-left: calc(var(--space-3) + 2.75rem);
        }

        .page-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
</style>
