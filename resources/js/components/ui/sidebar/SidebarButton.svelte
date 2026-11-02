<!--
  @component Prominent action row for the sidebar, in the accent tint the avatar
  uses (e.g. "New chat"). Follows the nav row metrics of SidebarItem.
-->
<script lang="ts">
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    interface Props extends HTMLButtonAttributes {
        /** Hugeicons icon shown before the label. */
        icon: IconComponent;
        /** Text label. */
        label: string;
        /** Keyboard shortcut hint, shown at the row's end on hover or focus. */
        shortcut?: string;
    }

    const {icon: Icon, label, shortcut, class: className, ...rest}: Props = $props();
</script>

<button
    type="button"
    {...mergeProps(rest, {class: ['sidebar-button', className]})}
>
    <span class="icon-wrap">
        <Icon size={18} strokeWidth={2} aria-hidden="true" />
    </span>
    <span class="label">{label}</span>
    {#if shortcut}
        <span class="shortcut" aria-hidden="true">{shortcut}</span>
    {/if}
</button>

<style>
    .sidebar-button {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        width: 100%;
        min-height: var(--nav-row-h);
        padding: 0 var(--space-3) 0 var(--nav-item-pad-x);
        overflow: hidden;
        flex-shrink: 0;
        border: 0;
        border-radius: var(--corner-sm);
        background: var(--color-accent-100);
        color: var(--color-accent-text);
        font: inherit;
        font-size: var(--nav-font-size);
        font-weight: var(--font-weight-medium);
        text-align: left;
        white-space: nowrap;
        cursor: pointer;
        transition: background var(--duration-fast);
    }

    /* Leans toward the text color, so it deepens the light tint. */
    .sidebar-button:hover {
        background: color-mix(in oklab, var(--color-accent-100) 85%, var(--color-accent-text));
    }

    /* The same hover as the accent button, which the send button uses. */
    :global(html.darkMode) .sidebar-button:hover {
        background: var(--color-accent-fill-hover);
    }

    .sidebar-button:focus-visible {
        outline: 2px solid var(--color-focus-ring, var(--color-interactive));
        outline-offset: 2px;
    }

    .icon-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--nav-icon-size);
        height: var(--nav-icon-size);
        flex-shrink: 0;
    }

    .label {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .shortcut {
        flex-shrink: 0;
        margin-left: auto;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-normal);
        letter-spacing: 0.03em;
        opacity: 0;
        transition: opacity var(--duration-fast);
    }

    .sidebar-button:hover .shortcut,
    .sidebar-button:focus-visible .shortcut {
        opacity: 1;
    }

    /* Bumped up a notch for easier tapping on small screens, like SidebarItem. The
       grown glyph spills out of its pinned column, so the margin returns the spill. */
    @media (--bp-md-and-smaller) {
        .sidebar-button {
            font-size: var(--font-size-sm);
        }

        .icon-wrap {
            margin-right: calc((var(--space-5) - var(--nav-icon-size)) / 2);
        }

        .icon-wrap :global(svg) {
            width: var(--space-5);
            height: var(--space-5);
        }
    }
</style>
