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
    <p class="u-label u-text-muted">{label}</p>
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
    .u-label{
        margin-bottom: var(--space-1_5);
    }
</style>