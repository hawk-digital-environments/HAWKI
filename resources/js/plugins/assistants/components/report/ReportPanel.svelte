<script lang="ts">

    import type {IconComponent} from '$lib/components/ui/icons';

    import type {Snippet} from "svelte";

    let {
        label,
        icon,
        description,
        display = "grid",
        children
    } = $props<{
        label?: string;
        icon?: IconComponent;
        description?: string;
        display?: "column" | "row" | "grid";
        children?: Snippet;
    }>();

</script>

<div class="report-panel">
    <div class="header">
        {#if icon}
            {@const IconCmp = icon}
            <span class="icon"><IconCmp size="1em" /></span>
        {/if}
        <span class="label">{label}</span>
    </div>
    {#if description}
        <div class="description">{description}</div>
    {/if}
    {#if children}
        <div class="content"
             class:displayCol={ display === "column"}
             class:displayRow={ display === "row"}
             class:displayGrid={ display === "grid"}
        >
            {@render children()}
        </div>
    {/if}
</div>


<style>

    .report-panel {
        display: flex;
        flex-direction: column;
        padding: var(--space-5) var(--space-6);
        border: var(--divider);
        border-radius: var(--corner-md);
        background: var(--panel-bg);
    }
    .description{
        margin: var(--space-4) 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
    .content.displayGrid {
        margin-top: var(--space-4);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(12rem, 20rem), 1fr));
        gap: var(--space-4);
    }
    .content.displayRow {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: var(--space-4);
        align-items: center;
        justify-content: space-between;
    }
    .content.displayCol {
        display: flex;
        flex-direction: column;
        margin-top: var(--space-4);
    }

    .header {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
    .header .icon {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-size-base);
        line-height: 1;
        color: var(--color-text-muted);
    }
    .header .label {
        line-height: 1;
    }

</style>
