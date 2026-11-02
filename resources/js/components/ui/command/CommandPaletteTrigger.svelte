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
    import ArrowDown01Icon from '$lib/components/ui/icons/iconset/ArrowDown01Icon.svelte';
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
            <span class="indicator" aria-hidden="true">
                <ArrowDown01Icon size={16} strokeWidth={2} />
            </span>
        </button>
    {/snippet}
</Tooltip>

<style>
    .section-trigger {
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        /* Hugs its content; a long label ellipsizes once the container runs out. */
        width: fit-content;
        min-width: 0;
        max-width: 100%;
        /* The shared row height, so the trigger sits in the same rhythm as the
           rows below it. */
        min-height: var(--nav-row-h);
        /* Left padding lands the icon on the shared rail column. Deliberately
           unchanged in the rail: the icon keeps the exact same offset from the
           panel edge in both states, so it never drifts sideways while the
           column animates. */
        padding: 0 var(--space-2_5) 0 var(--nav-item-pad-x);
        border: none;
        background: transparent;
        color: var(--color-nav-text);
        /* PageHeaderBar's title treatment, a step smaller. */
        font-size: var(--font-size-base);
        line-height: var(--line-height-tight);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        text-align: left;
        cursor: pointer;
        border-radius: var(--corner-sm);
        transition:
            background var(--duration-fast),
            color var(--duration-fast);
    }

    /* Open: the trigger takes the same wash *and* text colour the sidebar gives
       its active row, so an open palette reads as "you are here" in the nav's
       own language. */
    .section-trigger[data-state='open'] {
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
        transition: color var(--duration-fast);
    }

    .label {
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
        color: var(--nav-icon-color);
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

    /* With no label to match, the icon joins the rail's other icons: receded at
       rest, forward on hover (same as the header actions), and open styling
       otherwise. */
    .section-trigger.collapsed .icon-wrap {
        color: var(--nav-icon-color);
    }

    .section-trigger.collapsed:hover .icon-wrap {
        color: var(--color-text);
    }

    .section-trigger.collapsed[data-state='open'] .icon-wrap {
        color: inherit;
    }

    @media (--bp-md-and-smaller) {
        .icon-wrap :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }
</style>
