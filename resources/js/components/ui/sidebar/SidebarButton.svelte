<!--
  @component Prominent action row for the sidebar, carrying the brand gradient
  (e.g. "New chat"). Follows the nav row metrics of SidebarItem: in the
  collapsed rail it shrinks to an icon-only square and shows its label as a
  tooltip anchored to the icon instead.
-->
<script lang="ts">
    import type {HTMLButtonAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';

    interface Props extends HTMLButtonAttributes {
        /** Hugeicons icon shown before the label. */
        icon: IconComponent;
        /** Text label; doubles as the rail tooltip and accessible name. */
        label: string;
    }

    const {icon: Icon, label, class: className, ...rest}: Props = $props();

    const sidebar = useSidebar();
    const collapsed = $derived(!sidebar.navOpen);

    // Anchor it to the icon. The rail row still spans the full panel width. 
    let iconEl = $state<HTMLElement | null>(null);
</script>

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
            aria-label={label}
            {...mergeProps(props, rest, {class: ['sidebar-button', className]})}
            class:collapsed
        >
            <span class="icon-wrap" bind:this={iconEl}>
                <Icon size={18} strokeWidth={2} aria-hidden="true" />
            </span>
            <span class="label">{label}</span>
        </button>
    {/snippet}
</Tooltip>

<style>
    .sidebar-button {
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        width: 100%;
        min-height: var(--nav-row-h);
        padding: 0 var(--space-2_5) 0 var(--nav-item-pad-x);
        overflow: hidden;
        flex-shrink: 0;
        border: 0;
        border-radius: var(--corner-sm);
        background: linear-gradient(
            135deg,
            var(--gradient-brand-1),
            var(--gradient-brand-2) 55%,
            var(--gradient-brand-3)
        );
        color: var(--color-on-accent-fill);
        font: inherit;
        font-size: var(--font-size-xs);
        text-align: left;
        white-space: nowrap;
        cursor: pointer;
        transition: filter var(--duration-fast);
    }

    /* The ramp deepens on hover rather than being washed out by a flat overlay. */
    .sidebar-button:hover {
        filter: brightness(0.92) saturate(1.08);
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
        transition: opacity 160ms ease 100ms;
    }

    .sidebar-button.collapsed .label {
        opacity: 0;
        pointer-events: none;
        transition: opacity 100ms ease;
    }
</style>
