<script lang="ts">
    import Badge from '$lib/components/ui/badge/Badge.svelte';
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    let{
        version,
        date,
        changedKeys,
        isCreation
    } = $props <{
        version: string;
        date: string;
        /** Assistant fields this revision touched; `null`/empty when none were recorded. */
        changedKeys?: string[] | null;
        /** True for the oldest entry in the timeline (the initial creation). */
        isCreation?: boolean;
    }>();

    const {__} = useTranslator();
</script>


<div class="version-card">
    <div class="indicator">
        <div class="circle"></div>
        <div class="line"></div>
    </div>
    <div class="content">
        <div class="header">
            <span class="version">{version}</span>
            <span>.</span>
            <span class="date">{date}</span>
        </div>
        {#if changedKeys && changedKeys.length > 0}
            <div class="fields">
                <span class="fields-label">{__('assistants.detail.changed_fields')}</span>
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
    .version-card {
        display: flex;
        flex-direction: row;
        width: 100%;
        height: fit-content;
    }
    .indicator{
        display: flex;
        flex-direction: column;
        align-items: center;
        align-content: center;
        width: 3rem;
        min-width: 3rem;
        /*padding-top: .5rem;*/
    }
    .indicator .circle {
        width: 10px;
        height: 10px;
        aspect-ratio: 1/1;
        border-radius: 50%;
        background-color: gray;
    }
    .indicator .line {
        width: 1px;
        background-color: gray;
        height: 100%;
    }
    .version-card:first-child .indicator .circle {
        background-color: var(--color-accent-500);
    }
    .version-card:last-child .indicator .line {
        display: none;
    }


    .content{
        margin-top: -5px;
        margin-bottom: 1.5rem;
        width: 90%;
        max-height: 5rem;
        /*overflow: hidden;*/
    }
    .header{
        display: flex;
        gap: .5rem;
        height: 1.5rem;
        padding: 0;
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: var(--font-size-xs);
    }
    .version{
        font-weight: bold;
    }
    .fields{
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: var(--space-1);
        margin-top: var(--space-1);
    }
    .fields-label{
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        margin-right: var(--space-1);
    }
    .note{
        font-size: var(--font-size-xs);
        padding: 0;
        margin: 0;
        color: var(--color-text-muted);
    }

</style>
