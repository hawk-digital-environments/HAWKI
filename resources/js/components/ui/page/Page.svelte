<!--
  @component Shared page skeleton for content rendered inside the app layout's
  main area, generalizing the chat-page structure: an optional fixed header bar
  (the chat header's counterpart) above a single scroll region.

  Stacking works purely by document order, without any z-index: the body is
  rendered first and the header LAST, while grid-template-areas pins the header
  back to the top row — so the positioned header (with the default bar's
  blurred backdrop pseudo) paints above everything the body contains,
  positioned cards included, and the backdrop-filter can sample all of it as
  its backdrop. Inside the header, the backdrop pseudo precedes the bar's
  content row in tree order, so the bar text paints above the backdrop. The
  cost of the document order is that the bar follows the content for screen
  readers and tabbing.

  The `header` snippet (when given) renders bare into the header row — for
  bars that bring their own chrome, like the chat's ChatHeader. The default
  title row carries the shell's own bar styling. The `body` snippet
  (when given) replaces the shell's scroll region wholesale — for pages whose
  body is a canvas of multiple layers, like the chat's scroll region plus
  floating composer dock.

  The bar reserves room beside the floating mobile nav trigger (see the
  md-and-smaller padding on the content row) and carries its own blurred fade,
  so pages with a bar mark themselves `data-content-fade="none"` — the bar owns
  the top zone. Bar-less pages instead reserve scroll-away space on the scroll
  region and pick their content-fade variant via the `fade` prop ('short' for
  hero-led pages, the tall default for text-led ones).

  Pages keep their own content column (`.page-content`, `.messages`, …) inside
  the scroll region; the shell owns only the skeleton.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLElement> {
        /** Standard bar title; renders the shell's default title row. */
        title?: string;
        /** Custom bar content; renders bare (own chrome) into the header row and
         *  replaces the default title row. */
        header?: Snippet;
        /** Custom body; replaces the shell's scroll region — for multi-layer
         *  bodies like the chat's scroll region plus floating composer dock. */
        body?: Snippet;
        /** Page content, rendered inside the scroll region. */
        children?: Snippet;
        /** Content-fade variant for bar-less pages: 'short' keeps a hero visible
         *  under a small band; the default is the tall text-led band. */
        fade?: 'short' | 'none';
    }

    const {title, header, body, children, fade, class: className, ...rest}: Props = $props();

    const hasBar = $derived(!!header || !!title);
</script>

<section
    {...rest}
    class={['page', hasBar && 'with-header', className]}
    data-content-fade={hasBar ? 'none' : fade}
>
    <div class="page-body">
        {#if body}
            {@render body()}
        {:else}
            <div class="page-scroll" class:reserved={!hasBar}>
                {#if children}
                    {@render children()}
                {/if}
            </div>
        {/if}
    </div>
    {#if hasBar}
        <header class="page-header" class:default-bar={!header}>
            {#if header}
                {@render header()}
            {:else}
                <div class="page-header-bar">
                    <h2 class="page-title">{title}</h2>
                </div>
            {/if}
        </header>
    {/if}
</section>

<style>
    .page {
        display: grid;
        grid-template-rows: minmax(0, 1fr);
        grid-template-areas: 'body';
        height: 100%;
        min-height: 0;
        background: var(--color-surface-raised);
    }

    .page.with-header {
        grid-template-rows: auto minmax(0, 1fr);
        grid-template-areas: 'header' 'body';
    }

    /* Deliberately rendered AFTER the body in the document and pinned to the
       top row via grid-template-areas: a positioned element later in the
       document paints above everything earlier — in-flow content and
       positioned elements alike — with no z-index, and gives the backdrop
       filter the full page as its backdrop to sample. */
    .page-header {
        position: relative;
        grid-area: header;
    }

    /* Blurred backdrop in the chat header's visual language, for the default
       title bar only: the pseudo extends past the bar and fades out, so
       content scrolling underneath dissolves rather than hitting a line.
       Being positioned and first in tree order, it paints below the bar's
       content row and above the page content. Custom header snippets bring
       their own chrome including their own fade — a second band here would
       stack with it and double-veil the content below. */
    .page-header.default-bar::before {
        content: '';
        position: absolute;
        inset: 0 0 -3rem;
        pointer-events: none;
        background: color-mix(in oklch, var(--panel-bg) 88%, transparent);
        backdrop-filter: blur(12px);
        /* Eased ramp (rather than one linear stop) so neither the start nor the
           end of the fade shows a visible edge. */
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

    /* The bar's content row: positioned (and later in tree order than the
       backdrop pseudo) so it paints above the backdrop by natural paint
       order. */
    .page-header-bar {
        position: relative;
        display: flex;
        min-height: 3.75rem;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-2) var(--space-5);
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

    /* Kept non-positioned so its content stays in the ordinary paint flow —
       the header's document-order win covers it entirely. */
    .page-body {
        grid-area: body;
        min-height: 0;
    }

    /* The page's single scroll region (default body). */
    .page-scroll {
        height: 100%;
        overflow-y: auto;
        overflow-x: hidden;
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

        /* Bar-less pages: scroll-away reserve so at-rest content clears the
           trigger while still scrolling up under the content fade. */
        .page-scroll.reserved {
            padding-top: calc(var(--space-2_5) + var(--nav-row-h) + var(--space-2));
        }
    }

    @media print {
        .page, .page-body, .page-scroll { display: block; height: auto; overflow: visible; }
    }
</style>
