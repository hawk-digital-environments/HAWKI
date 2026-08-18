<script lang="ts">
import ReleaseStage from "$lib/plugins/assistants/components/assistantBuilderComponents/ReleaseStage.svelte";
import ReportPanel from "$lib/plugins/assistants/components/report/ReportPanel.svelte";
import ReportCard from "$lib/plugins/assistants/components/report/ReportCard.svelte";
import StatusCard from "$lib/plugins/assistants/components/report/StatusCard.svelte";
import ChecklistItem from "$lib/plugins/assistants/components/report/ChecklistItem.svelte";
import InfoPanel from "$lib/plugins/assistants/components/info/InfoPanel.svelte";
import BuilderInput from "$lib/plugins/assistants/components/assistantBuilderComponents/BuilderInput.svelte";
import Button from "$lib/components/ui/button/Button.svelte";
import FloppyDiskIcon from "$lib/components/ui/icons/iconset/FloppyDiskIcon.svelte";
import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
import { ReleaseMode } from "$lib/plugins/assistants/types/assistant/ReleaseMode";
import { ValidationState } from "$lib/plugins/assistants/types/enums/ValidationState";
import Shield01Icon from "$lib/components/ui/icons/iconset/Shield01Icon.svelte";
import TaskEdit01Icon from "$lib/components/ui/icons/iconset/TaskEdit01Icon.svelte";
import CheckmarkCircle01Icon from "$lib/components/ui/icons/iconset/CheckmarkCircle01Icon.svelte";
import CheckListIcon from "$lib/components/ui/icons/iconset/CheckListIcon.svelte";
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";
const {__} = useTranslator();
const builder = useBuilderContext();
let assistant = $derived(builder.draft);

const statusLabels: Record<ReleaseMode, string> = {
    [ReleaseMode.DRAFT]: __('assistants.builder.publish.status.draft'),
    [ReleaseMode.PRIVATE]: __('assistants.builder.publish.status.private'),
    [ReleaseMode.ORGANIZATIONAL]: __('assistants.builder.publish.status.organizational'),
    [ReleaseMode.FEDERATED]: __('assistants.builder.publish.status.federated'),
};

const visibilityLabels: Record<ReleaseMode, string> = {
    [ReleaseMode.DRAFT]: __('assistants.builder.publish.visibility.draft'),
    [ReleaseMode.PRIVATE]: __('assistants.builder.publish.visibility.private'),
    [ReleaseMode.ORGANIZATIONAL]: __('assistants.builder.publish.visibility.organizational'),
    [ReleaseMode.FEDERATED]: __('assistants.builder.publish.visibility.federated'),
};

const publishabilityLabels: Record<ReleaseMode, string> = {
    [ReleaseMode.DRAFT]: __('assistants.builder.publish.publishability.draft'),
    [ReleaseMode.PRIVATE]: __('assistants.builder.publish.publishability.private'),
    [ReleaseMode.ORGANIZATIONAL]: __('assistants.builder.publish.publishability.organizational'),
    [ReleaseMode.FEDERATED]: __('assistants.builder.publish.publishability.federated'),
};

let statusLabel = $derived(statusLabels[assistant.releaseStage]);
let visibility = $derived(visibilityLabels[assistant.releaseStage]);
let publishability = $derived(publishabilityLabels[assistant.releaseStage]);

// The review triggers and the "start review" hint only apply to release paths
// that actually kick off a review (organisational / federated).
let requiresReview = $derived(
    assistant.releaseStage === ReleaseMode.ORGANIZATIONAL ||
    assistant.releaseStage === ReleaseMode.FEDERATED
);

let saveAsText = $derived.by(() => {
    switch (builder.draft.releaseStage) {
        case ReleaseMode.PRIVATE:
            return __('assistants.builder.publish.save_as_private_draft');
        case ReleaseMode.ORGANIZATIONAL:
        case ReleaseMode.FEDERATED:
            return __('assistants.builder.publish.save_as_review');
        case ReleaseMode.DRAFT:
            return __('assistants.builder.publish.save_as_draft');
        default:
            return '';
    }
});





</script>

<div class="page-wrapper">
    <div class="page-content">

        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.publish.title')}</h3>
            <p class="page-description">{__('assistants.builder.publish.description')}</p>
        </div>

        <ReleaseStage/>

        <!--  ------------------------------------   -->

        <ReportPanel
            label={__('assistants.builder.publish.risk.title')}
            icon={Shield01Icon}
            description={__('assistants.builder.publish.risk.description')}
            display="grid"
        >

            <ReportCard
                label={__('assistants.builder.publish.risk.label_status')}
            >
                <StatusCard
                        render="roundEdge"
                        label={assistant.status ?? statusLabel}
                        icon={TaskEdit01Icon}
                />
            </ReportCard>

            <ReportCard
                label={__('assistants.builder.publish.risk.label_risk_level')}
            >
                <StatusCard
                        render="roundEdge"
                        label={__('assistants.builder.publish.risk.risk_level_low')}
                        icon={TaskEdit01Icon}
                        type={ValidationState.SAFE}
                />
            </ReportCard>

            <ReportCard
                label={__('assistants.builder.publish.risk.label_visibility')}
            >
                {visibility}
            </ReportCard>

            <ReportCard
                label={__('assistants.builder.publish.risk.label_publishability')}
            >
                {publishability}
            </ReportCard>

        </ReportPanel>

        <!--  ------------------------------------   -->

        <ReportPanel
                label={__('assistants.builder.publish.completeness.title')}
                icon={CheckmarkCircle01Icon}
                description={__('assistants.builder.publish.completeness.description')}
                display="column"
        >
            {#each builder.validator.completenessGroups as group}
                <div class="completeness-group">
                    <p class="label faded group-heading">{group.group}</p>
                    {#each group.items as item (item.id)}
                        <ChecklistItem
                                label={item.label}
                                description={item.description}
                                status={item.status}/>
                    {/each}
                </div>
            {/each}
        </ReportPanel>

        <!--  ------------------------------------   -->

        {#if requiresReview}
            <ReportPanel
                label={__('assistants.builder.publish.triggers.title')}
                icon={CheckListIcon}
                display="column"
            >
                {#each builder.validator.triggers as item (item.id)}
                    <ChecklistItem
                            label={item.label}
                            description={item.description}
                            status={item.status}/>
                {/each}
            </ReportPanel>

            <InfoPanel icon={CheckListIcon}>
                <p class="label">
                    {__('assistants.builder.publish.review_info.title')}
                </p>
                <p>
                   <span class="faded">{__('assistants.builder.publish.review_info.description')}</span>
                    <span class="faded">{__('assistants.builder.publish.review_info.note')}</span>
                </p>
            </InfoPanel>
        {/if}

        <!--  ------------------------------------   -->

        <BuilderInput
            type="textarea"
            label={__('assistants.builder.publish.input_version_note')}
            name="versionshinweis"
            placeholder={__('assistants.builder.publish.input_version_note_placeholder')}
            assistantValueKey="submissionNote"
            />


        <Button
            variant="fill"
            size="md"
            block
            iconLeft={FloppyDiskIcon}
            disabled={builder.draft.releaseStage === ReleaseMode.DRAFT}
            onclick={() => {builder.requestRelease()}}
        >{saveAsText}</Button>

    </div>
</div>

<style>
    /* Separate consecutive completeness groups so their headings read as
       distinct sections; the first group hugs the panel description. */
    .completeness-group + .completeness-group {
        margin-top: var(--space-4);
    }
    .group-heading {
        margin-bottom: var(--space-2);
    }
</style>
