<script lang="ts">

    import Alert01Icon from '$lib/components/ui/icons/iconset/Alert01Icon.svelte';
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import CheckmarkCircle02Icon from '$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte';
    import CircleIcon from '$lib/components/ui/icons/iconset/CircleIcon.svelte';
    import type {IconComponent} from '$lib/components/ui/icons';
    import {ValidationState} from "$lib/types/enums/ValidationState";

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
            <span class="icon {status}"><ItemIcon size="1em" /></span>
        {:else if status === ValidationState.WARNING}
            <span class="icon {status}"><Alert01Icon size="1em" /></span>
        {:else if status === ValidationState.ERROR}
            <span class="icon {status}"><AlertCircleIcon size="1em" /></span>
        {:else if status === ValidationState.SAFE}
            <span class="icon {status}"><CheckmarkCircle02Icon size="1em" /></span>
        {:else if status === ValidationState.UNKNOWN}
            <span class="icon {status}"><CircleIcon size="1em" /></span>
        {/if}
    </div>


    <div class="text-wrapper">
        <p class="label label-xs">{label}</p>
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
    .checklist-card.reportCard .icon{
        margin: 0;
    }
    .checklist-card.reportCard .icon-wrapper{
        display: flex;
        align-items: center;
    }

    .text-wrapper{
        min-width: 0;
    }
    .text-wrapper .label{
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


    .icon{
        font-size: var(--font-size-lg);
        line-height: 1;
    }
    .icon.warning{
        color: var(--color-warning);
    }
    .icon.error{
        color: var(--color-error);
    }
    .icon.safe{
        color: var(--color-success);
    }
    .icon.unknown{
        color: var(--color-text);
    }
</style>
