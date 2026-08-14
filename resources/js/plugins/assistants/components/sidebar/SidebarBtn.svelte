<!-- $lib/components/presets/SidebarBtn.svelte -->
<script lang="ts">

    import type {IconComponent} from '$lib/components/ui/icons';
    import { page } from '$app/state';
    import {goto} from "$app/navigation";

    let { href,
        label,
        icon,
        disabled = false
    } = $props<{
        href: string;
        label: string;
        icon?: IconComponent;
        disabled?: boolean;
    }>();

    let isActive = $derived(page.url.pathname === href);

    function onclick(){
        goto(href);
    }
</script>

<button {onclick}
        class="sidebar-btn"
        class:active={isActive}
        disabled={disabled}
>
    {#if icon}
        {@const IconCmp = icon}
        <span class="icon"><IconCmp size="1em" /></span>
    {/if}
    <span class="label label-xs label-thin">{label}</span>
</button>

<style>
    button{
        display: flex;
        column-gap: .75rem;
        height: 2rem;
        padding: 0 .75rem;
        text-align: left;
        align-items: center;
        background: none;
        transition: all var(--transition-fast);
        cursor: pointer;
        border-radius: var(--border-radius-normal);
    }
    button:hover {
        background: var(--panel-main);
    }
    button.active{
        color: var(--accent-color);
        background: var(--panel-main);
    }
    button.active .label{
        font-weight: bold;
    }
    button.active .icon{
        color: var(--accent-color);
    }
    .icon {
        font-size: 1.25rem;
        transition: all var(--transition-medium);

    }
</style>
