<script lang="ts">
    import type {Snippet} from "svelte";

    let {
        label,
        children,
    }= $props<{
        label: string;
        children: Snippet;
    }>();

</script>

<div class="report-card">
    <p class="label label-xs label-thin faded">{label}</p>
    <div class="report">
        {@render children()}
    </div>
</div>

<style>
    .report{
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
    .label{
        margin-bottom: var(--space-1_5);
    }
</style>