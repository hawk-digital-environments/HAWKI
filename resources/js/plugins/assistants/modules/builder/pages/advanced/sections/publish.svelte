<script lang="ts">
import ReleaseStage from "$plugins/assistants/modules/builder/components/ReleaseStage.svelte";
import ReportPanel from "$plugins/assistants/components/report/ReportPanel.svelte";
import ReportCard from "$plugins/assistants/components/report/ReportCard.svelte";
import StatusCard from "$plugins/assistants/components/report/StatusCard.svelte";
import ChecklistItem from "$plugins/assistants/components/report/ChecklistItem.svelte";
import Alert from "$lib/components/ui/alert/Alert.svelte";
import BuilderInput from "$plugins/assistants/modules/builder/components/BuilderInput.svelte";
import Button from "$lib/components/ui/button/Button.svelte";
import FloppyDiskIcon from "$lib/components/ui/icons/iconset/FloppyDiskIcon.svelte";
import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
import { ReleaseMode } from "$plugins/assistants/types/assistant/ReleaseMode";
import { ReviewStage } from "$plugins/assistants/types/assistant/ReviewStage";
import { ValidationState } from "$plugins/assistants/types/enums/ValidationState";
import Shield01Icon from "$lib/components/ui/icons/iconset/Shield01Icon.svelte";
import TaskEdit01Icon from "$lib/components/ui/icons/iconset/TaskEdit01Icon.svelte";
import CheckmarkCircle01Icon from "$lib/components/ui/icons/iconset/CheckmarkCircle01Icon.svelte";
import CheckListIcon from "$lib/components/ui/icons/iconset/CheckListIcon.svelte";
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";

/**
 * The kernel's route renderer instantiates page components without passing
 * any props (see core's ChatIndex.svelte), so this interface is intentionally
 * empty.
 */
interface Props {
}

const {}: Props = $props();
const {__} = useTranslator();
const builder = useBuilderContext();
let assistant = $derived(builder.draft);

const statusLabels: Record<ReleaseMode, string> = {
    [ReleaseMode.DRAFT]: __('assistants.builder.publish.status.draft'),
    [ReleaseMode.PRIVATE]: __('assistants.builder.publish.status.private'),
    [ReleaseMode.ORGANIZATIONAL]: __('assistants.builder.publish.status.organizational'),
    [ReleaseMode.FEDERATED]: __('assistants.builder.publish.status.federated'),
};

let statusLabel = $derived(statusLabels[assistant.releaseStage]);

// Denial states from the creator's own review (include=assistant_review).
// DENIED is permanent: choices, note and submit are hidden. NEEDS_REVISION is
// a soft denial: the reason is shown, the submit controls remain.
let review = $derived(assistant.review ?? null);
let permanentlyDenied = $derived(review?.status === ReviewStage.DENIED);
let needsRevision = $derived(review?.status === ReviewStage.NEEDS_REVISION);
let denialReason = $derived(
    (permanentlyDenied || needsRevision) ? (review?.reason ?? null) : null
);

let reviewStatusCard = $derived.by(() => {
    if (permanentlyDenied) {
        return {
            label: __('assistants.builder.publish.status.denied'),
            type: ValidationState.ERROR,
        };
    }
    if (needsRevision) {
        return {
            label: __('assistants.builder.publish.status.needs_revision'),
            type: ValidationState.WARNING,
        };
    }
    return {label: statusLabel, type: ValidationState.INFO};
});

// The review triggers and the "start review" hint only apply to release paths
// that actually kick off a review (organisational / federated).
let requiresReview = $derived(
    assistant.releaseStage === ReleaseMode.ORGANIZATIONAL ||
    assistant.releaseStage === ReleaseMode.FEDERATED
);

let saveAsText = $derived.by(() => {
    switch (assistant.releaseStage) {
        case ReleaseMode.PRIVATE:
            return __('assistants.builder.publish.save_as_private_assistant');
        case ReleaseMode.ORGANIZATIONAL:
        case ReleaseMode.FEDERATED:
            return __('assistants.builder.publish.save_as_review');
        case ReleaseMode.DRAFT:
            return __('assistants.builder.publish.keep_as_draft');
        default:
            return '';
    }
});





</script>

<div class="page-wrapper">
    <div class="page-content">

        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.publish.title')}</h3>
            <p class="page-description">
                {permanentlyDenied
                    ? __('assistants.builder.publish.denied.description')
                    : __('assistants.builder.publish.description')}
            </p>
        </div>

        {#if needsRevision}
            <Alert
                icon={TaskEdit01Icon}
                size="small"
                title={__('assistants.builder.publish.needs_revision.title')}
                description={__('assistants.builder.publish.needs_revision.hint')}
            />
        {/if}

        {#if denialReason}
            <div class="denial-reason">
                <p class="u-label">{__('assistants.builder.publish.denied.reason_label')}</p>
                <p class="reason-text">{denialReason}</p>
            </div>
        {/if}

        {#if !permanentlyDenied}
            <ReleaseStage/>
        {/if}

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
                        label={reviewStatusCard.label}
                        icon={TaskEdit01Icon}
                        type={reviewStatusCard.type}
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
                    <p class="u-label u-text-muted group-heading">{group.group}</p>
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

            <Alert
                icon={CheckListIcon}
                size="small"
                title={__('assistants.builder.publish.review_info.title')}
                description={`${__('assistants.builder.publish.review_info.description')} ${__('assistants.builder.publish.review_info.note')}`}
            />
        {/if}

        <!--  ------------------------------------   -->

        {#if !permanentlyDenied}
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
                onclick={() => {builder.requestRelease()}}
            >{saveAsText}</Button>
        {/if}

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
    .denial-reason {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }
    .denial-reason .reason-text {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
</style>
