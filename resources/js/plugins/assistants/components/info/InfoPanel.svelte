<script lang="ts">

    import type {IconComponent} from '$lib/components/ui/icons';

    import type {Snippet} from "svelte";

    let {
        icon,
        children
    } = $props<{
        icon: IconComponent;
        children: Snippet;
    }>();


</script>

<div class="info-panel">
    <div class="content">
        {#if icon}
            {@const IconCmp = icon}
            <div class="icon-wrapper">
                <span class="icon"><IconCmp size="1em" /></span>
            </div>
        {/if}
        <div class="text-content">
            {@render children()}
        </div>
    </div>
</div>

<style>
    .info-panel {
        padding: 1rem 1rem 0 1.5rem;
        border: var(--border);
        border-color: color-mix(in oklch, var(--color-info) 35%, transparent);
        border-radius: var(--corner-md);
        background-color: color-mix(in oklch, var(--color-info) 6%, transparent);
    }
    .content {
        display: flex;
        flex-direction: row;
        column-gap: .5rem;
    }
    .icon-wrapper {
        display: flex;

        justify-content: center;
    }
    .icon {
        font-size: var(--font-size-lg);
        color: var(--color-info);
    }

</style>
