<!--
  @component The control that opens a `CommandPalette` used as a switcher: it
  reads as "the thing you're currently on", and clicking it (or hitting ⌘K)
  lists what you can switch to. Shaped as a sidebar row so the nav rhythm stays
  intact — resting flat on the panel, lifting only on hover/open.

  Spread the palette's `props` onto it so bits-ui can wire up the popover; the
  `data-state` that comes with them is what paints the open styling.

  Usage:
    <CommandPalette items={items} bind:open {current} onSelect={select}>
        {#snippet trigger({props})}
            <CommandPaletteTrigger
                label={activeItem.label}
                icon={activeItem.icon}
                collapsed={!sidebar.navOpen}
                {...props}
            />
        {/snippet}
    </CommandPalette>
-->
<script lang="ts">
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import UnfoldMoreIcon from '$lib/components/ui/icons/iconset/UnfoldMoreIcon.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';

    interface Props extends HTMLButtonAttributes {
        /** Text of the entry the trigger currently stands for. */
        label: string;
        /** Icon of that entry, shown before the label. */
        icon?: IconComponent;
        /** Icon-only rail form: the label moves into a tooltip. */
        collapsed?: boolean;
    }

    let {label, icon: Icon, collapsed = false, class: className, ...rest}: Props = $props();

    // The rail trigger still spans the full panel width, so a tooltip anchored
    // to the button would open a row's width away from the icon it describes.
    // Anchor it to the icon instead.
    let iconEl = $state<HTMLElement | null>(null);
</script>

<!-- The tooltip is switched off rather than unmounted while expanded: one DOM
     branch for both states keeps the button mounted across a collapse, so the
     label and indicator fade out instead of the row being rebuilt mid-animation. -->
<Tooltip
    tooltip={label}
    side="right"
    sideOffset={24}
    delayDuration={300}
    customAnchor={iconEl}
    disabled={!collapsed}
>
    {#snippet children({props})}
        <button
            type="button"
            aria-label={collapsed ? label : undefined}
            {...mergeProps(props, rest, {class: ['section-trigger', className]})}
            class:collapsed
        >
            <span class="icon-wrap" bind:this={iconEl}>
                {#if Icon}
                    <Icon size={18} strokeWidth={2} aria-hidden="true" />
                {/if}
            </span>
            <span class="label">{label}</span>
            <!-- Double chevron: the trigger opens a list you pick from rather
                 than a menu that drops down, so it points both ways. -->
            <span class="indicator" aria-hidden="true">
                <UnfoldMoreIcon size={16} strokeWidth={2} />
            </span>
        </button>
    {/snippet}
</Tooltip>

<style>
    .section-trigger {
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        width: 100%;
        /* The shared row height, so the trigger sits in the same rhythm as the
           rows below it. */
        min-height: var(--nav-row-h);
        /* Left padding lands the icon on the shared rail column; the border is
           part of that offset, so it comes out of the padding. Deliberately
           unchanged in the rail: the icon keeps the exact same offset from the
           panel edge in both states, so it never drifts sideways while the
           column animates. */
        padding: 0 var(--space-2) 0 calc(var(--nav-item-pad-x) - var(--divider-width));
        border: var(--divider);
        background: transparent;
        color: var(--color-text);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        text-align: left;
        cursor: pointer;
        border-radius: var(--corner-sm);
        transition:
            background var(--duration-fast),
            border-color var(--duration-fast),
            color var(--duration-fast);
    }

    /* Hover swaps the outline for the neutral wash — one emphasis at a time. */
    .section-trigger:hover {
        border-color: transparent;
        background: var(--color-hover);
    }

    /* Open: the trigger takes the same wash *and* text colour the sidebar gives
       its active row, so an open palette reads as "you are here" in the nav's
       own language. The outline drops out — the fill is the emphasis. */
    .section-trigger[data-state='open'] {
        border-color: transparent;
        background: var(--color-active-surface);
        color: var(--color-active-text);
    }

    .section-trigger:focus-visible {
        outline: 2px solid var(--color-accent-fill);
        outline-offset: 1px;
    }

    .icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        /* Out fast, in late — matching the nav rows: the text is gone well
           before the rail is narrow enough to squeeze it. */
        transition: opacity 160ms ease 100ms;
    }

    /* Open/close affordance. It recedes at rest so the label carries the row,
       and comes forward once the row is hovered or the palette is open. */
    .indicator {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--color-text-muted);
        transition:
            color var(--duration-fast),
            opacity 160ms ease 100ms;
    }

    .section-trigger[data-state='open'] .indicator,
    .section-trigger:hover .indicator {
        color: inherit;
    }

    /* Rail: only the section icon shows. Label and indicator fade out and are
       clipped by the panel rather than being pulled from the flow. */
    .section-trigger.collapsed .label,
    .section-trigger.collapsed .indicator {
        opacity: 0;
        pointer-events: none;
        transition: opacity 100ms ease;
    }

    @media (--bp-md-and-smaller) {
        .section-trigger {
            font-size: var(--font-size-sm);
        }

        .icon-wrap :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }
</style>
