<!--
  @component Square icon button for the sidebar's chrome: the header's search
  and close buttons, the add button beside the space tabs. A row tall, so it
  lines up with the rows; its icon recedes at rest and comes forward on hover,
  like the rows' icons. `label` names it for screen readers and in its tooltip.

  Spread a menu's trigger `props` onto it to use it as that menu's trigger: it
  holds its hover look while the menu is open.

  @example
  ```svelte
  <SidebarIconButton icon={Search01Icon} label="Search" shortcut="K" onclick={openSearch}/>
  ```
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    interface Props extends HTMLButtonAttributes {
        icon: IconComponent;
        /** Accessible name, and the tooltip unless `tooltip` is given. */
        label: string;
        /** Richer tooltip content, e.g. the label with its shortcut. */
        tooltip?: Snippet;
        /** Key hint, e.g. `K`, shown in place of the icon while `.shortcuts-held`
         *  is set on an ancestor. */
        shortcut?: string;
    }

    const {icon: Icon, label, tooltip, shortcut, class: className, ...rest}: Props = $props();
</script>

<Tooltip tooltip={tooltip ?? label} side="bottom" delayDuration={300}>
    {#snippet children({props})}
        <!-- The caller's props go last, so its `id` and a menu's `data-state` win over
             the tooltip trigger's own; event handlers from both still run. -->
        <button
            {...mergeProps(props, rest, {class: ['sidebar-icon-button', className]})}
            type="button"
            aria-label={label}
        >
            <span class="icon-cell">
                <span class="glyph"><Icon size={18} strokeWidth={2} aria-hidden="true"/></span>
                {#if shortcut}
                    <span class="shortcut" aria-hidden="true">{shortcut}</span>
                {/if}
            </span>
        </button>
    {/snippet}
</Tooltip>

<style>
    .sidebar-icon-button {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--nav-row-h);
        height: var(--nav-row-h);
        padding: 0;
        border: none;
        background: transparent;
        color: var(--color-nav-icon);
        cursor: pointer;
        border-radius: var(--corner-sm);
        transition:
            color var(--duration-fast),
            background var(--duration-fast);
    }

    .sidebar-icon-button:hover,
    .sidebar-icon-button[data-state='open'] {
        color: var(--color-text);
        background: var(--color-hover);
    }

    .sidebar-icon-button:focus-visible {
        outline: 2px solid var(--color-accent-fill);
        outline-offset: -2px;
    }

    /* The icon and its shortcut share one cell, so the button never resizes when
       they swap. */
    .icon-cell {
        display: inline-grid;
        place-items: center;
    }

    .glyph,
    .shortcut {
        grid-area: 1 / 1;
        display: inline-flex;
    }

    .shortcut {
        font-size: var(--font-size-xs);
        visibility: hidden;
    }

    :global(.shortcuts-held) .icon-cell:has(.shortcut) .glyph {
        visibility: hidden;
    }

    :global(.shortcuts-held) .shortcut {
        visibility: visible;
    }

    /* Bumped up a notch for easier tapping on small screens; the box needs no
       override, as `--nav-row-h` grows there and takes it along. */
    @media (--bp-md-and-smaller) {
        .glyph :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }
</style>
