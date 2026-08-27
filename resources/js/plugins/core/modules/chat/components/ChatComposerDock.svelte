<!--
@component Floating composer dock shared by the chat pages. Pins its children
(the composer) above the page's scroll region, fades the content out behind
the disclaimer strip, and measures two layout values the pages depend on: its
own height (bindable `height`, which the pages reserve as bottom padding in
the scroll content) and the scroll region's scrollbar width (mirrored as right
padding so the composer stays aligned with the centred message column).
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Scroll region behind the dock — used to measure the scrollbar gutter. */
        scrollRegion?: HTMLElement | null;
        /** Measured dock height in px (bindable, written by the dock). */
        height?: number;
        /** The composer, rendered in a row mirroring a message's grid. */
        children: Snippet;
    }

    let {scrollRegion = null, height = $bindable(0), children, class: className, ...restProps}: Props = $props();
    const {__} = useTranslator();

    let dock = $state<HTMLDivElement | null>(null);
    let scrollbarGutter = $state(0);

    $effect(() => {
        const element = dock;
        const region = scrollRegion;
        if (typeof ResizeObserver === 'undefined') return;

        // The dock floats above the scroll region, so the pages reserve its
        // height as bottom padding while the composer grows with input.
        let dockObserver: ResizeObserver | null = null;
        if (element) {
            height = element.offsetHeight;
            dockObserver = new ResizeObserver(() => height = element.offsetHeight);
            dockObserver.observe(element);
        }

        // Mirror the scroll region's scrollbar width so the composer stays
        // aligned with the centred message column.
        let regionObserver: ResizeObserver | null = null;
        if (region) {
            scrollbarGutter = region.offsetWidth - region.clientWidth;
            regionObserver = new ResizeObserver(() => scrollbarGutter = region.offsetWidth - region.clientWidth);
            regionObserver.observe(region);
        }

        return () => {
            dockObserver?.disconnect();
            regionObserver?.disconnect();
            height = 0;
            scrollbarGutter = 0;
        };
    });
</script>

<div {...restProps} class={["composer-dock u-print-hidden", className]} bind:this={dock} style:--scrollbar-gutter="{scrollbarGutter}px">
    <div class="composer-row">
        {@render children()}
    </div>
    <p class="disclaimer">{__('chat.page.disclaimer')}</p>
</div>

<style>
    .composer-dock {
        position: absolute;
        inset-inline: 0;
        bottom: 0;
        padding-bottom: var(--space-3);
        padding-right: var(--scrollbar-gutter, 0px);
        /* Own stacking context so the ::before backdrop (z-index -1) sits
           behind the composer but still above the scroll region. */
        isolation: isolate;
        /* Let wheel/click events in the gutters reach the chat behind the
           dock; the composer row and disclaimer stay interactive. */
        pointer-events: none;
    }

    /* Blurred, tinted backdrop behind the whole dock so no message text is
       readable underneath the composer; the mask fades it in softly at the
       top edge. Stops short of the scrollbar so it doesn't get tinted. */
    .composer-dock::before {
        content: '';
        position: absolute;
        inset: 0;
        right: var(--scrollbar-gutter, 0px);
        /* Backdrop inside the dock's stacking context. */
        --composer-dock-fade-z: -1;
        z-index: var(--composer-dock-fade-z);
        background: linear-gradient(to top, var(--color-surface-raised) 45%, color-mix(in srgb, var(--color-surface-raised) 70%, transparent) 85%, transparent);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        mask-image: linear-gradient(to top, black 80%, transparent);
        -webkit-mask-image: linear-gradient(to top, black 80%, transparent);
    }

    .composer-dock > * { pointer-events: auto; }

    /* Slightly wider than a message row (52rem) so the composer card
       overhangs the message column on both sides. */
    .composer-row {
        width: min(100%, 56rem);
        margin-inline: auto;
        padding-inline: var(--space-3);
    }

    .composer-row :global(.chat-composer-wrapper) {
        max-width: none;
        min-width: 0;
    }

    .disclaimer {
        margin: var(--space-2) 0 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        text-align: center;
    }

    @media (--bp-sm-and-smaller) {
        .composer-row { padding-inline: var(--space-1); }
    }
</style>
