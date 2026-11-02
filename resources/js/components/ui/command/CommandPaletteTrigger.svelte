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

    interface Props extends HTMLButtonAttributes {
        /** Text of the entry the trigger currently stands for. */
        label: string;
        /** Icon of that entry, shown before the label. */
        icon?: IconComponent;
        /** Shortcut of that entry; replaces the chevron while `.shortcuts-held`
         *  is set on an ancestor. */
        shortcut?: string;
    }

    let {label, icon: Icon, shortcut, class: className, ...rest}: Props = $props();
</script>

<button
    type="button"
    {...mergeProps(rest, {class: ['section-trigger', className]})}
>
    <span class="icon-wrap">
        {#if Icon}
            <Icon size={18} strokeWidth={2} aria-hidden="true" />
        {/if}
    </span>
    <span class="label">{label}</span>
    <span class="indicator" aria-hidden="true">
        <span class="chevron">
            <ArrowDown01Icon size={16} strokeWidth={2} />
        </span>
        {#if shortcut}
            <span class="shortcut">{shortcut}</span>
        {/if}
    </span>
</button>

<style>
    .section-trigger {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        /* Hugs its content; a long label ellipsizes once the container runs out. */
        width: fit-content;
        min-width: 0;
        max-width: 100%;
        /* The shared row height, so the trigger sits in the same rhythm as the
           rows below it. */
        min-height: var(--nav-row-h);
        padding: 0 var(--space-3) 0 var(--nav-item-pad-x);
        border: none;
        /* The same colors as the sidebar's action button (SidebarButton). */
        background: var(--color-accent-100);
        color: var(--color-accent-text);
        font-size: var(--nav-font-size);
        font-weight: var(--font-weight-medium);
        text-align: left;
        cursor: pointer;
        border-radius: var(--corner-sm);
        transition:
            background var(--duration-fast),
            color var(--duration-fast);
    }

    /* An open palette holds the hover color. */
    .section-trigger:hover,
    .section-trigger[data-state='open'] {
        background: color-mix(in oklab, var(--color-accent-100) 85%, var(--color-accent-text));
    }

    :global(html.darkMode) .section-trigger:hover,
    :global(html.darkMode) .section-trigger[data-state='open'] {
        background: var(--color-accent-fill-hover);
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
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* The chevron and shortcut share one cell sized to the wider of the two, so
       the trigger keeps its width when they swap. */
    .indicator {
        display: inline-grid;
        align-items: center;
        justify-items: end;
        flex-shrink: 0;
    }

    .chevron {
        display: inline-flex;
        grid-area: 1 / 1;
    }

    .shortcut {
        grid-area: 1 / 1;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-normal);
        letter-spacing: 0.03em;
        white-space: nowrap;
        visibility: hidden;
    }

    :global(.shortcuts-held) .shortcut {
        visibility: visible;
    }

    :global(.shortcuts-held) .indicator:has(.shortcut) .chevron {
        visibility: hidden;
    }

    /* Bumped up a notch for easier tapping on small screens, like the sidebar's
       rows. */
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
