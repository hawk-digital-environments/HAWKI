<!--
  @component One tab of `CollapsibleTabs`. Not used on its own: the strip owns
  selection, focus and the highlights behind the tabs, and hands each tab what it
  needs to know.

  A tab with an icon collapses to that icon unless it is selected; its label
  slides open on a spring to its measured width, so the tabs beside it move over
  instead of jumping. A collapsed tab names itself in a tooltip.
-->
<script module lang="ts">
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    export interface CollapsibleTabItem {
        /** Stable identifier for the tab, used as the strip's `value`. */
        key: string;
        /** Visible label; the only one shown while the tab is selected. */
        label: string;
        /** Icon the tab collapses to. Without one, the label always shows. */
        icon?: IconComponent;
        /** Short hint, e.g. `1`, shown in place of the icon while `.shortcuts-held`
         *  is set on an ancestor. */
        shortcut?: string;
    }
</script>

<script lang="ts">
    import type {Attachment} from 'svelte/attachments';
    import {Spring} from 'svelte/motion';
    import {mergeProps} from 'bits-ui';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {springs} from '$lib/utils/springs.js';
    import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte.js';

    interface Props {
        item: CollapsibleTabItem;
        /** Whether this is the selected tab. */
        selected: boolean;
        /** Whether a highlight sits behind the tab (selected or pointed at). */
        active: boolean;
        /** Whether the tab is the strip's one tab stop. */
        focusable: boolean;
        /** Registers the button with the strip. */
        register: Attachment<HTMLButtonElement>;
        onselect: () => void;
        onkeydown: (event: KeyboardEvent) => void;
    }

    const {item, selected, active, focusable, register, onselect, onkeydown}: Props = $props();

    const reducedMotion = useReducedMotion();

    const expanded = $derived(selected || !item.icon);

    // Animated to a measured width rather than `auto`: `bind:offsetWidth` reads the
    // layout box, so a transform on the way up (a popup's opening scale) can't skew it.
    let naturalWidth = $state(0);
    const labelWidth = new Spring(0, springs.fast);
    let placed = false;

    $effect(() => {
        if (!naturalWidth) return;
        // The first measurement lands in place; only later changes slide.
        labelWidth.set(expanded ? naturalWidth : 0, {instant: !placed || reducedMotion.current});
        placed = true;
    });

    // Until the label has been measured, CSS sizes it, so a selected tab doesn't
    // grow in from nothing on first paint.
    const clipWidth = $derived(naturalWidth ? `${labelWidth.current}px` : expanded ? 'auto' : '0px');
</script>

<Tooltip tooltip={item.label} disabled={expanded} side="bottom" delayDuration={300}>
    {#snippet children({props})}
        <button
            {...mergeProps(props, {onclick: onselect, onkeydown})}
            type="button"
            role="tab"
            class="tab"
            class:active
            class:selected
            aria-selected={selected}
            tabindex={focusable ? 0 : -1}
            {@attach register}
        >
            {#if item.icon}
                <span class="icon-cell">
                    <span class="glyph"><item.icon size={18} strokeWidth={2} aria-hidden="true"/></span>
                    {#if item.shortcut}
                        <span class="shortcut" aria-hidden="true">{item.shortcut}</span>
                    {/if}
                </span>
            {/if}
            <span class="label-clip" class:shown={expanded} style:width={clipWidth}>
                <span class="label" bind:offsetWidth={naturalWidth}>{item.label}</span>
            </span>
        </button>
    {/snippet}
</Tooltip>

<style>
    /* The icon sits centred in a row-high square, so a collapsed tab is square; the
       label clip carries its own gap, so it adds nothing until it opens. */
    .tab {
        --tab-icon-size: var(--nav-icon-size);

        position: relative;
        /* Above the highlights, which paint at 0. */
        z-index: 1;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        height: var(--nav-row-h);
        padding: 0 calc((var(--nav-row-h) - var(--tab-icon-size)) / 2);
        border: none;
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-nav-icon);
        font-family: inherit;
        font-size: var(--nav-font-size);
        font-weight: var(--font-weight-medium);
        cursor: pointer;
        /* At the fast spring's pace, so the color lands with the highlight. */
        transition: color 80ms ease;
    }

    .tab.active {
        color: var(--color-accent-text);
    }

    /* Inside, since the strip scrolls and would clip a ring drawn outside. */
    .tab:focus-visible {
        outline: 2px solid var(--color-accent-fill);
        outline-offset: -2px;
    }

    /* The icon and its shortcut share one cell, so swapping them never moves the
       label. */
    .icon-cell {
        display: inline-grid;
        place-items: center;
        width: var(--tab-icon-size);
        flex-shrink: 0;
    }

    .glyph,
    .shortcut {
        grid-area: 1 / 1;
        display: inline-flex;
    }

    .shortcut {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-normal);
        visibility: hidden;
    }

    :global(.shortcuts-held) .icon-cell:has(.shortcut) .glyph {
        visibility: hidden;
    }

    :global(.shortcuts-held) .shortcut {
        visibility: visible;
    }

    .label-clip {
        display: flex;
        overflow: hidden;
        opacity: 0;
        transition: opacity 60ms ease;
    }

    .label-clip.shown {
        opacity: 1;
    }

    /* The gap to the icon and the extra room at the end ride along with the label,
       so they open and close with it. */
    .label {
        flex: none;
        padding-inline: var(--space-2) var(--space-1);
        white-space: nowrap;
    }

    /* No icon, so no gap to keep from one. */
    .label-clip:first-child .label {
        padding-inline-start: 0;
    }

    /* Bumped up a notch for easier tapping on small screens, like the sidebar's
       rows; the padding follows the bigger icon, so the tab stays square. */
    @media (--bp-md-and-smaller) {
        .tab {
            --tab-icon-size: var(--space-5);
            font-size: var(--font-size-sm);
        }

        .glyph :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .tab,
        .label-clip {
            transition: none;
        }
    }
</style>
