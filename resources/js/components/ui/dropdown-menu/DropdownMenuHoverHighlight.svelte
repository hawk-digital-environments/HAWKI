<!--
  @component Magnetic hover for a dropdown panel: one highlight that glides to the
  row nearest the pointer, the same behaviour as the sidebar's `MenuList`. Render
  it as a direct child of the panel, before the rows. Rows are found by their menu
  role, so every item type in the family takes part without registering.

  While it is showing, the rows' own highlighted background is switched off through
  `data-pointer-hover` on the panel. Arrow keys clear it, so the keyboard keeps the
  rows' regular highlight.
-->
<script lang="ts">
    import type {Attachment} from 'svelte/attachments';
    import {createProximityHover} from '$lib/utils/proximityHover.svelte.js';

    const ROW_SELECTOR = '[role^="menuitem"]:not([data-disabled])';

    const hover = createProximityHover();
    let panel = $state<HTMLElement | null>(null);
    let rows: HTMLElement[] = [];

    // Queried rather than registered, so rows folding away or being filtered need
    // no bookkeeping.
    function syncRows() {
        if (!panel) return;
        rows = Array.from(panel.querySelectorAll<HTMLElement>(ROW_SELECTOR));
        hover.setItems(rows);
    }

    const attachAnchor: Attachment<HTMLElement> = (anchor) => {
        const element = anchor.parentElement;
        if (!element) return;
        panel = element;
        hover.setContainer(element);

        const clear = () => hover.handlers.onmouseleave();
        const onmouseenter = () => {
            syncRows();
            hover.handlers.onmouseenter();
        };
        const onmousemove = (event: MouseEvent) => {
            syncRows();
            // The slack keeps the highlight on across the gap around a separator.
            if (hover.isNearItem(event)) hover.handlers.onmousemove(event);
            else clear();
        };

        element.addEventListener('mouseenter', onmouseenter);
        element.addEventListener('mousemove', onmousemove);
        element.addEventListener('mouseleave', clear);
        element.addEventListener('keydown', clear);
        return () => {
            element.removeEventListener('mouseenter', onmouseenter);
            element.removeEventListener('mousemove', onmousemove);
            element.removeEventListener('mouseleave', clear);
            element.removeEventListener('keydown', clear);
            hover.setContainer(null);
            panel = null;
        };
    };

    $effect(() => {
        panel?.toggleAttribute('data-pointer-hover', hover.activeIndex !== null);
    });

    const rect = $derived(hover.activeIndex !== null ? hover.itemRects[hover.activeIndex] : null);
    const destructive = $derived(
        hover.activeIndex !== null && !!rows[hover.activeIndex]?.classList.contains('variant--destructive')
    );
</script>

<span class="anchor" {@attach attachAnchor}></span>
{#if rect && panel}
    <!-- Rects are measured from the panel's border box; absolute offsets start inside its border. -->
    <span
        class="hover-bg"
        class:destructive
        style:top="{rect.top - panel.clientTop}px"
        style:left="{rect.left - panel.clientLeft}px"
        style:width="{rect.width}px"
        style:height="{rect.height}px"
    ></span>
{/if}

<style>
    .anchor {
        display: none;
    }

    /* Paints below the rows, which are all positioned and come later in the panel. */
    .hover-bg {
        position: absolute;
        pointer-events: none;
        background: var(--color-hover);
        border-radius: var(--corner-sm);
        transition:
            top 200ms var(--easing-spring),
            height 200ms var(--easing-spring);
    }

    .hover-bg.destructive {
        background: color-mix(in oklch, var(--color-error) 10%, transparent);
    }

    /* An open submenu's trigger keeps its own background. */
    :global(.dropdown-content[data-pointer-hover] [role^='menuitem'][data-highlighted]:not([data-state='open'])) {
        background-color: transparent;
    }
</style>
