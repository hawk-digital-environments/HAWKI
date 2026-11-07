<script lang="ts">

    import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";
    import type {Creator} from "$plugins/assistants/types/assistant/Creator";
    import GitPullRequestArrowIcon from "$lib/components/ui/icons/iconset/GitPullRequestArrowIcon.svelte";
    import InformationCircleIcon from "$lib/components/ui/icons/iconset/InformationCircleIcon.svelte";
    import BadgeCheckIcon from "$lib/components/ui/icons/iconset/BadgeCheckIcon.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
    import {ReleaseMode} from "$plugins/assistants/types/assistant";


    const {__} = useTranslator()

    let {
        remixedAssistant,
        creator,
    } = $props<{
        remixedAssistant: Assistant;
        creator: Creator | null;
    }>();

    /** The original's release stage, in the detail menu's own wording. */
    const stageLabel = $derived(
        remixedAssistant.releaseStage
            ? __(`assistants.detail.release_${remixedAssistant.releaseStage}`)
            : '—'
    );
    const stageIsPublic = $derived(
        remixedAssistant.releaseStage === ReleaseMode.ORGANIZATIONAL ||
        remixedAssistant.releaseStage === ReleaseMode.FEDERATED
    );


</script>



<!-- A flat neutral tile like the rest of the detail view. Top: what this
     assistant was remixed from (badge, name, original creator), with the
     original's release stage set apart on the right (its label is for
     screen readers only).
     Below a hairline: the review hint as the tile's footnote. -->
<section class="remix-details-card">
    <div class="row">
        <span class="badge" aria-hidden="true"><GitPullRequestArrowIcon size="1em" /></span>
        <div class="source">
            <p class="name">{__("assistants.remix.remix_from")} {remixedAssistant.name}</p>
            <!-- `creator` is nullable: the list's copy of an assistant (shown in
                 the detail modal while it loads) carries no remix creator. -->
            {#if creator}
                <p class="creator">
                    {__("assistants.remix.remix_from_creator")}
                    <span class="creator-name">{creator.displayName}</span>
                </p>
            {/if}
        </div>
        <div class="original-status">
            <span class="u-sr-only">{__("assistants.remix.original_status")}</span>
            <!-- Same small pill as the cards' category tag; only the icon
                 carries the green/amber verdict. (Not StatusCard: its label
                 is a `.u-label`, which the utilities layer sizes up.) -->
            <span class="stage" class:public={stageIsPublic}>
                <span class="stage-icon"><BadgeCheckIcon size="1em" /></span>
                {stageLabel}
            </span>
        </div>
    </div>
    <p class="remix-hint">
        <span class="icon"><InformationCircleIcon size="1em" /></span>
        <span>{__("assistants.remix.remix_hint")}</span>
    </p>
</section>


<style>
    .remix-details-card{
        display: flex;
        flex-direction: column;
        padding: var(--space-4);
        border-radius: var(--corner-md);
        background-color: var(--color-surface-light);
    }
    .row{
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: var(--space-3);
    }
    .badge{
        display: flex;
        flex: none;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: var(--corner-full);
        background-color: var(--color-surface-raised);
        color: var(--color-text);
        font-size: var(--font-size-base);
    }
    .source{
        flex: 1;
        min-width: 12rem;
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
    }
    /* Tight lines, so title and author read as one block. */
    .source p{
        margin: 0;
        line-height: var(--line-height-tight);
    }
    .name{
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
    .original-status{
        display: flex;
        flex: none;
    }
    .creator{
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    .creator-name{
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
    .stage{
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        height: 1.5rem;
        /* Less on the icon side: a round icon reads as inset already, so even
           padding would look lopsided. */
        padding: 0 var(--space-2_5) 0 var(--space-1_5);
        border-radius: var(--corner-full);
        background-color: var(--color-surface-raised);
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium);
        line-height: 1;
        white-space: nowrap;
    }
    .stage-icon{
        display: flex;
        font-size: var(--font-size-xs);
        color: var(--color-warning);
    }
    .stage.public .stage-icon{
        color: var(--color-success);
    }
    /* Footnote under a hairline, across the whole tile. */
    .remix-hint{
        display: flex;
        gap: var(--space-1_5);
        align-items: flex-start;
        margin: var(--space-4) 0 0;
        padding-top: var(--space-3);
        border-top: var(--divider);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }
    .remix-hint .icon{
        display: flex;
        flex: none;
        /* Centre the icon on the first line of text. */
        height: calc(1em * var(--line-height-normal));
        align-items: center;
    }
</style>
