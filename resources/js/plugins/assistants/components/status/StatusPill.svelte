<script lang="ts">
    import type {IconComponent} from "$lib/components/ui/icons";

    /**
     * Generic semantic status pill (icon + label) used for release-stage and
     * risk-level badges. Replaces the previously duplicated
     * ReleaseStageStatus/RiskStatus styling: those components now only map
     * their domain enum onto this pill's props.
     */
    let {
        label,
        icon,
        tone = "info",
    } = $props<{
        label: string;
        icon?: IconComponent;
        tone?: "info" | "safe" | "warning" | "error";
    }>();
</script>

{#if icon}
    {@const StatusIcon = icon}
    <span class="status-pill" data-tone={tone}>
        <span class="icon"><StatusIcon size="1em" /></span>
        <span class="label">{label}</span>
    </span>
{:else}
    <span class="status-pill" data-tone={tone}>
        <span class="label">{label}</span>
    </span>
{/if}

<style>
    /* Self-contained, content-hugging pill. Height comes only from the padding
       and the single line of text — there is no fixed height to fight over. */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        width: fit-content;
        max-width: 100%;
        padding: var(--space-1) var(--space-2);
        border: none;
        border-radius: var(--corner-full);
        font-size: var(--font-size-xxs);
        line-height: 1;
        white-space: nowrap;
    }
    .icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: var(--font-size-xs);
        line-height: 0;
    }
    .label {
        line-height: 1;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .status-pill[data-tone="info"] {
        background: color-mix(in oklch, var(--color-info) 12%, transparent);
        color: color-mix(in oklch, var(--color-info) 75%, var(--color-text));
    }
    .status-pill[data-tone="safe"] {
        background: color-mix(in oklch, var(--color-success) 12%, transparent);
        color: color-mix(in oklch, var(--color-success) 70%, var(--color-text));
    }
    .status-pill[data-tone="warning"] {
        background: color-mix(in oklch, var(--color-warning) 14%, transparent);
        color: color-mix(in oklch, var(--color-warning) 78%, var(--color-text));
    }
    .status-pill[data-tone="error"] {
        background: color-mix(in oklch, var(--color-error) 12%, transparent);
        color: color-mix(in oklch, var(--color-error) 72%, var(--color-text));
    }
</style>
