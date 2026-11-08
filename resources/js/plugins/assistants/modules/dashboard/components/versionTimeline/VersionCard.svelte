<script lang="ts">
    import Badge from '$lib/components/ui/badge/Badge.svelte';
    import OverflowTooltip from '$lib/components/ui/tooltip/OverflowTooltip.svelte';
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    let{
        version,
        date,
        changedKeys,
        isCreation,
        note
    } = $props <{
        version: string;
        date: string;
        /** Assistant fields this revision touched; `null`/empty when none were recorded. */
        changedKeys?: string[] | null;
        /** True for the oldest entry in the timeline (the initial creation). */
        isCreation?: boolean;
        /** The creator's version note ("What has changed?") recorded on release. */
        note?: string;
    }>();

    const {__} = useTranslator();
</script>


<!-- One entry of the change history: a dot on a continuous track, the
     version and date on the dot's line, what changed underneath. -->
<div class="version-card">
    <span class="dot" aria-hidden="true"></span>
    <div class="content">
        <p class="header">
            <span class="version">v{version}</span>
            <span class="date">{date}</span>
        </p>
        {#if note}
            <div class="fields">
                <span class="u-sr-only">{__('assistants.detail.version_note')}</span>
                <Badge variant="secondary" style="max-width: 100%; min-width: 0;">
                    <OverflowTooltip value={note}/>
                </Badge>
            </div>
        {/if}
        {#if changedKeys && changedKeys.length > 0}
            <div class="fields">
                <span class="u-sr-only">{__('assistants.detail.changed_fields')}</span>
                {#each changedKeys as field (field)}
                    <Badge variant="secondary">{field}</Badge>
                {/each}
            </div>
        {:else if isCreation}
            <p class="note">{__('assistants.detail.creation')}</p>
        {/if}
    </div>
</div>

<style>
    /* Neutral and minimal: a border-grey track, quiet grey dots, and the
       current version marked in the text color. */
    .version-card {
        --row: 1.5rem;          /* height of the version/date line */
        --dot: 0.625rem;        /* column width = the current version's dot */
        position: relative;
        display: grid;
        grid-template-columns: var(--dot) minmax(0, 1fr);
        column-gap: var(--space-3);
        padding-bottom: var(--space-4);
    }
    .version-card:last-child {
        padding-bottom: 0;
    }

    /* The track: from this dot's centre down to the next dot's centre, so the
       line runs unbroken through the whole history. */
    .version-card:not(:last-child)::before {
        content: '';
        position: absolute;
        left: calc(var(--dot) / 2 - 1px);
        top: calc(var(--row) / 2);
        bottom: calc(var(--row) / -2);
        width: 2px;
        border-radius: var(--corner-full);
        background-color: var(--color-border);
    }

    /* Older versions: smaller, softer dots sitting on the track; the current
       one full size and full strength. */
    .dot {
        position: relative;
        justify-self: center;
        width: 0.5rem;
        height: 0.5rem;
        margin-top: calc((var(--row) - 0.5rem) / 2);
        border-radius: var(--corner-full);
        background-color: var(--color-text-disabled);
    }
    .version-card:first-child .dot {
        width: var(--dot);
        height: var(--dot);
        margin-top: calc((var(--row) - var(--dot)) / 2);
        background-color: var(--color-text);
    }

    .content {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        min-width: 0;
    }
    .header {
        display: flex;
        align-items: baseline;
        gap: var(--space-2);
        padding-top: calc(var(--row) / 2 - 0.5em);
        box-sizing: border-box;
        height: var(--row);
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: 1;
    }
    .version {
        font-weight: var(--font-weight-semibold);
        color: var(--color-text);
        font-variant-numeric: tabular-nums;
    }
    /* Secondary to the version: smaller and muted. */
    .date {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .fields {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-1);
    }
    .note {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
</style>
