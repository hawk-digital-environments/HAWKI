<!--
  @component Shared page skeleton for content rendered inside the app layout's
  main area, generalizing the chat-page structure: an optional fixed header bar
  (the chat header's counterpart) above a single scroll region.

  The header comes FIRST in the document — title and bar controls precede the
  content for screen readers and tabbing. Paint priority comes from one local
  z-index on the bar (PageHeaderBar): the whole bar unit, blurred backdrop
  included, paints above everything the body contains, positioned cards
  included, and the backdrop-filter can sample all of it as its backdrop.
  The default bar's row and backdrop chrome live in PageHeaderBar
  (shared with the chat header), which isolates its own stacking so the
  backdrop stays behind the bar content.

  The `header` snippet (when given) becomes the header row's grid child
  directly, element and all — for bars that bring their own chrome, like the
  chat's ChatHeader (auto-placement puts it in the header row since the body
  area is claimed by name). The default title row is a plain
  `<PageHeaderBar heading={title}>`; the bar chrome itself — row metrics,
  backdrop, mobile reserve, spacer variant — lives in PageHeaderBar, which
  custom bars like ChatHeader compose as their root. The `body` snippet
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
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';

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
    {#if hasBar}
        {#if header}
            {@render header()}
        {:else}
            <PageHeaderBar heading={title} />
        {/if}
    {/if}
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
