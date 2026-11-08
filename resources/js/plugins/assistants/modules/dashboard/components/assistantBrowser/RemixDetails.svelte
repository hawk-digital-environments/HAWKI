<script lang="ts">

    import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";
    import type {Creator} from "$plugins/assistants/types/assistant/Creator";
    import GitPullRequestArrowIcon from "$lib/components/ui/icons/iconset/GitPullRequestArrowIcon.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";


    const {__} = useTranslator()

    let {
        remixedAssistant,
        creator,
    } = $props<{
        remixedAssistant: Assistant;
        creator: Creator | null;
    }>();


</script>



<!-- One quiet line: what this assistant was remixed from, and by whom. -->
<p class="remix-details">
    <span class="icon" aria-hidden="true"><GitPullRequestArrowIcon size="1em" /></span>
    <span>
        {__("assistants.remix.remix_from")} <strong>{remixedAssistant.name}</strong>
        <!-- `creator` is nullable: the list's copy of an assistant (shown in
             the detail modal while it loads) carries no remix creator. -->
        {#if creator}
            · {__("assistants.remix.remix_from_creator")} <strong>{creator.displayName}</strong>
        {/if}
    </span>
</p>


<style>
    .remix-details{
        display: flex;
        align-items: flex-start;
        gap: var(--space-2);
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }
    .icon{
        display: flex;
        flex: none;
        /* Centre the icon on the first line of text. */
        height: calc(1em * var(--line-height-normal));
        align-items: center;
    }
    strong{
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
</style>
