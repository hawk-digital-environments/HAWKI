<!--
  @component Main content area. Fills the grid's `main` column and renders the
  page content next to the sidebar.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import PanelLeftIcon from '$lib/components/ui/icons/iconset/PanelLeftIcon.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';

    interface Props extends HTMLAttributes<HTMLElement> {
        /** Page content. */
        children: Snippet;
    }

    const {children: pageContent, class: className, ...restProps}: Props = $props();
    const sidebar = useSidebar();
    const {__} = useTranslator();
    const toggleLabel = $derived(sidebar.navOpen ? __('ui.navigation.close') : __('ui.navigation.open'));

    function toggleNavigation() {
        sidebar.toggleNav();
        // The mobile drawer covers this button, so focus moves to its own close.
        if (sidebar.mobile && sidebar.navOpen) {
            setTimeout(() => document.getElementById('app-navigation-toggle')?.focus());
        }
    }
</script>

<main
    {...restProps}
    id="main-content"
    class={["content", className]}
    tabindex="-1"
    inert={sidebar.mobile && sidebar.navOpen}
>
    <Tooltip tooltip={toggleLabel} side="bottom" sideOffset={4} delayDuration={300}>
        {#snippet children({props})}
            <button
                {...props}
                id="mobile-navigation-trigger"
                class="mobile-navigation-trigger u-print-hidden"
                type="button"
                aria-label={toggleLabel}
                aria-controls="app-navigation"
                aria-expanded={sidebar.navOpen}
                onclick={toggleNavigation}
            >
                <PanelLeftIcon size={18} strokeWidth={2} aria-hidden="true" />
            </button>
        {/snippet}
    </Tooltip>
    {@render pageContent()}
</main>

<style>
    .content {
        position: relative;
        grid-area: main;
        min-width: 0;
        overflow: hidden;
        background: var(--panel-bg);
    }

    .mobile-navigation-trigger {
        position: absolute;
        /* The same inset as the sidebar's padding. */
        top: var(--space-4);
        left: var(--space-4);
        /* Floats over the page content, outside the nav it opens and closes. */
        --mobile-navigation-trigger-z: 10;
        z-index: var(--mobile-navigation-trigger-z);
        display: inline-flex;
        width: var(--nav-row-h);
        height: var(--nav-row-h);
        padding: 0;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-nav-icon);
        cursor: pointer;
        transition: color var(--duration-fast);
    }

    .mobile-navigation-trigger:hover {
        background: var(--color-hover);
        color: var(--color-text);
    }

    @media (--bp-md-and-smaller) {
        .mobile-navigation-trigger :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }

        /* Mobile: the nav toggle floats over the top-left of the content. Overlay a
           gradient of the content background so content dissolves under it as it
           scrolls up rather than cutting off at a hard edge. The overlay is pinned
           to .content (not the scroll layer) so it works no matter which nested
           element actually scrolls, and sits below the trigger (z-index 10) but
           above the page content.

           Pages are expected to reserve room at the top so at-rest content starts
           *below* the overlay; anything that starts higher renders washed out. The
           tall default suits text-led pages (chat), where the extra dissolve is the
           point. Pages that lead with full-bleed art mark themselves
           `data-content-fade="short"` and get a band sized to the trigger instead,
           so a hero card keeps its colour and its rounded top corners. Pages whose
           top zone is owned by fixed chrome with its own fade (the chat header's
           blur) mark themselves `data-content-fade="none"` and get no band at all. */
        .content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: var(--content-fade-h, calc(var(--space-2_5) + 7rem));
            z-index: 5;
            pointer-events: none;
            --fade-color: var(--panel-bg);
            /* Eased fade. What reads as "not smooth" in an alpha ramp is a
               sudden change in slope, not the ramp itself: a solid band meeting
               a linear ramp creases where they join, and a linear ramp reaching
               zero leaves a faint tail edge. So sample smootherstep
               (6t^5 - 15t^4 + 10t^3), whose slope AND curvature are both zero at
               each end — it leaves the solid top and arrives at fully
               transparent with no detectable seam. Stops are the curve
               evaluated every 8%; even spacing keeps the segments short enough
               that the straight-line interpolation between them is invisible. */
            background: linear-gradient(
                to bottom,
                var(--fade-color) 0%,
                var(--fade-color) 8%,
                color-mix(in oklch, var(--fade-color) 97%, transparent) 16%,
                color-mix(in oklch, var(--fade-color) 91%, transparent) 24%,
                color-mix(in oklch, var(--fade-color) 81%, transparent) 32%,
                color-mix(in oklch, var(--fade-color) 68%, transparent) 40%,
                color-mix(in oklch, var(--fade-color) 54%, transparent) 48%,
                color-mix(in oklch, var(--fade-color) 39%, transparent) 56%,
                color-mix(in oklch, var(--fade-color) 25%, transparent) 64%,
                color-mix(in oklch, var(--fade-color) 14%, transparent) 72%,
                color-mix(in oklch, var(--fade-color) 6%, transparent) 80%,
                color-mix(in oklch, var(--fade-color) 2%, transparent) 88%,
                transparent 100%
            );
        }

        /* Sized to end where the reserved space ends (nav toggle inset + its
           height + the page's own top padding), so nothing is veiled at
           rest. The trigger is opaque in its own right, so the solid part of
           the band only has to cover the content immediately around it. */
        .content:has(:global([data-content-fade='short']))::before {
            --content-fade-h: calc(var(--space-2_5) + var(--nav-row-h) + var(--space-2));
        }

        /* Pages whose top zone is owned by fixed chrome that brings its own
           fade (e.g. the chat header's blur) suppress the overlay entirely —
           content there never scrolls through that zone, so the band would
           only veil the chrome at rest. */
        .content:has(:global([data-content-fade='none']))::before {
            --content-fade-h: 0rem;
        }
    }
</style>
