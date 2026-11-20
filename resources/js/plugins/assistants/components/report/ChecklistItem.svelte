<script lang="ts">

    import Alert01Icon from '$lib/components/ui/icons/iconset/Alert01Icon.svelte';
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import CheckmarkCircle02Icon from '$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte';
    import CircleIcon from '$lib/components/ui/icons/iconset/CircleIcon.svelte';
    import {StatusIcon} from '$lib/components/ui/icons';
    import type {IconComponent} from '$lib/components/ui/icons';
    import {ValidationState} from "$plugins/assistants/types/enums/ValidationState";

    type StatusTone = 'accent' | 'info' | 'success' | 'warning' | 'error' | 'neutral';

    function statusTone(status?: ValidationState): StatusTone {
        switch (status) {
            case ValidationState.SAFE: return 'success';
            case ValidationState.WARNING: return 'warning';
            case ValidationState.ERROR: return 'error';
            case ValidationState.INFO: return 'info';
            case ValidationState.UNKNOWN:
            case ValidationState.EMPTY: return 'neutral';
            default: return 'accent';
        }
    }

    let {
        label,
        description,
        icon,
        status,
        report
    }= $props <{
        label: string;
        description?: string;
        icon?: IconComponent;
        status?: ValidationState;
        report?: string;
    }>();

</script>


<div class="checklist-card"
     class:reportCard={report}
>
    <div class="icon-wrapper">
        {#if icon}
            {@const ItemIcon = icon}
            <StatusIcon icon={ItemIcon} tone={statusTone(status)} size="sm"/>
        {:else if status === ValidationState.WARNING}
            <StatusIcon icon={Alert01Icon} tone="warning" size="sm"/>
        {:else if status === ValidationState.ERROR}
            <StatusIcon icon={AlertCircleIcon} tone="error" size="sm"/>
        {:else if status === ValidationState.SAFE}
            <StatusIcon icon={CheckmarkCircle02Icon} tone="success" size="sm"/>
        {:else if status === ValidationState.UNKNOWN}
            <StatusIcon icon={CircleIcon} tone="neutral" size="sm"/>
        {/if}
    </div>


    <div class="text-wrapper">
        <p class="u-label">{label}</p>
        {#if description}
            <p class="description">{description}</p>
        {/if}
    </div>

    <p class="report {status}">{report}</p>

</div>


<style>

    .checklist-card{
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: start;
        flex-direction: row;
        gap: .5rem;
        margin-bottom: .5rem;
    }
    /* Center the icon on the first line of the label rather than nudging it
       with a fixed offset, so single- and multi-line rows both align. */
    .icon-wrapper{
        display: flex;
        align-items: center;
        min-height: 1.5rem;
    }
    .checklist-card.reportCard{
        align-items: center;
        padding: .25rem .5rem;
        height: 2.25rem;
        border-radius: var(--corner-md);
        background-color: var(--color-hover);
    }
    .checklist-card.reportCard .icon-wrapper{
        display: flex;
        align-items: center;
    }

    .text-wrapper{
        min-width: 0;
    }
    .text-wrapper .u-label{
        /* Match the icon-wrapper line box so the icon centers on this line. */
        min-height: 1.5rem;
        display: flex;
        align-items: center;
    }
    .description{
        font-size: var(--font-size-xs);
        padding: 0;
        margin: 0;
    }
    .report{
        font-size: var(--font-size-xs);
        margin: 0;
    }
    .report.warning{
        color: var(--color-text);
    }
    .report.error{
        color: var(--color-error);
    }
    .report.safe{
        color: var(--color-success);
    }
</style>
