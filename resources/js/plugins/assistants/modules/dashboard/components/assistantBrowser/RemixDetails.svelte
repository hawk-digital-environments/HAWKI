<script lang="ts">

    import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";
    import type {Creator} from "$plugins/assistants/types/assistant/Creator";
    import GitPullRequestArrowIcon from "$lib/components/ui/icons/iconset/GitPullRequestArrowIcon.svelte";
    import StatusCard from "$plugins/assistants/components/report/StatusCard.svelte";
    import {ValidationState} from "$plugins/assistants/types/enums/ValidationState";
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


</script>



<div class="remix-details-card">
    <div class="icon-wrapper">
        <span class="icon"><GitPullRequestArrowIcon size="1em" /></span>
    </div>
    <div class="details">
        <div class="assistant-info">
            <p class="name">{__("assistants.remix.remix_from")} {remixedAssistant.name}</p>
            <p class="creator">
                <span>
                    {__("assistants.remix.remix_from_creator")}
                </span>
                <span class="creator-name">{creator.displayName}</span>
            </p>
        </div>


        <div class="verification-state">
            <span>{__("assistants.remix.original_status")}</span>
            <StatusCard
                    label={remixedAssistant.releaseStage}
                    size="small"
                    icon={BadgeCheckIcon}
                    type={
                        (remixedAssistant.releaseStage === ReleaseMode.ORGANIZATIONAL ||
                        remixedAssistant.releaseStage === ReleaseMode.FEDERATED) ?
                            ValidationState.SAFE :
                            ValidationState.WARNING

                    }
            />
        </div>
        <div class="remix-hint">
            <span class="icon"><InformationCircleIcon size="1em" /></span>
            <span>{__("assistants.remix.remix_hint")}</span>
        </div>


    </div>
</div>


<style>
    .remix-details-card{
        display: flex;
        flex-direction: row;
        gap: var(--space-2_5);
        padding: var(--space-2_5);
        border: var(--border);
        border-radius: var(--corner-md);
        background-color: var(--color-hover);
    }
    .icon-wrapper{
        display: flex;
        flex-direction: column;
        padding: var(--space-1);
        color: var(--color-accent-500)
    }
    .details{
        display: flex;
        flex-direction: column;
        gap: var(--space-2_5);
    }
    .details p{
        margin: 0;
    }
    .details .name{
        font-weight: bold;
        font-size: var(--font-size-sm);
    }
    .details .creator {
        font-size: var(--font-size-sm);
        color: var(--color-text-mute)
    }
    .details .creator-name{
        font-weight: bold;
        color: var(--color-text)
    }

    .verification-state{
        display: flex;
        flex-direction: row;
        gap: var(--space-2_5);
        font-size: var(--font-size-xs);
    }


    .remix-hint{
        display: flex;
        flex-direction: row;
        gap: var(--space-1_5);
        align-items: start;
        font-size: var(--font-size-xs);
        line-height: var(--font-size-xs);
        justify-content: center;
        color: var(--color-accent-500)
    }
    .remix-hint .icon{
        display: flex;
        align-items: center;
    }



</style>
