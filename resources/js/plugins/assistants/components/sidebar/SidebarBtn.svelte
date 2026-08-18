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
        transition: all var(--duration-fast);
        cursor: pointer;
        border-radius: var(--corner-md);
    }
    button:hover {
        background: var(--color-hover);
    }
    button.active{
        color: var(--color-active-text);
        background: var(--color-active-surface);
    }
    button.active .label{
        font-weight: bold;
    }
    button.active .icon{
        color: var(--color-active-text);
    }
    .icon {
        font-size: 1.25rem;
        transition: all var(--duration-medium);

    }
</style>
