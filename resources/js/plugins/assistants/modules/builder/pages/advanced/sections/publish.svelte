<script lang="ts">
import ReleaseStage from "$plugins/assistants/modules/builder/components/ReleaseStage.svelte";
import Alert from "$lib/components/ui/alert/Alert.svelte";
import BuilderInput from "$plugins/assistants/modules/builder/components/BuilderInput.svelte";
import {useBuilderContext} from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
import { ReleaseMode } from "$plugins/assistants/types/assistant/ReleaseMode";
import { ReviewStage } from "$plugins/assistants/types/assistant/ReviewStage";
import { ValidationState } from "$plugins/assistants/types/enums/ValidationState";
import TaskEdit01Icon from "$lib/components/ui/icons/iconset/TaskEdit01Icon.svelte";
import CheckmarkCircle02Icon from "$lib/components/ui/icons/iconset/CheckmarkCircle02Icon.svelte";
import Alert01Icon from "$lib/components/ui/icons/iconset/Alert01Icon.svelte";
import AlertCircleIcon from "$lib/components/ui/icons/iconset/AlertCircleIcon.svelte";
import CircleIcon from "$lib/components/ui/icons/iconset/CircleIcon.svelte";
import type {IconComponent} from "$lib/components/ui/icons";
import InfoPopover from "$lib/components/ui/popover/InfoPopover.svelte";
import RadialProgress from "$lib/components/ui/radial-progress/RadialProgress.svelte";
import type {CheckItem} from "$plugins/assistants/modules/builder/contexts/builderValidationRules";
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

const checkIcons: Partial<Record<ValidationState, IconComponent>> = {
    [ValidationState.SAFE]: CheckmarkCircle02Icon,
    [ValidationState.WARNING]: Alert01Icon,
    [ValidationState.ERROR]: AlertCircleIcon,
};

let completedCount = $derived(builder.validator.completeness.filter(c => c.ok).length);
let totalCount = $derived(builder.validator.completeness.length);
let progress = $derived(totalCount ? completedCount / totalCount : 0);

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

</script>

{#snippet checkRow(item: CheckItem)}
    {@const Icon = checkIcons[item.status] ?? CircleIcon}
    <li class="check" data-tone={item.status}>
        <span class="check-icon" aria-hidden="true"><Icon size="1em"/></span>
        <div class="check-text">
            <span class="check-label">{item.label}</span>
            {#if item.description}
                <span class="check-description">{item.description}</span>
            {/if}
        </div>
        <span class="check-group">{item.group}</span>
    </li>
{/snippet}

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

        <section class="overview" aria-label={__('assistants.builder.publish.risk.title')}>
            <div class="tiles">
                <div class="tile">
                    <span class="tile-label">{__('assistants.builder.publish.risk.label_status')}</span>
                    <span class="tile-value" data-tone={reviewStatusCard.type}>
                        <span class="dot" aria-hidden="true"></span>{reviewStatusCard.label}
                    </span>
                </div>
                <div class="tile">
                    <span class="tile-label">
                        {__('assistants.builder.publish.risk.label_risk_level')}
                        <InfoPopover
                            label={__('assistants.builder.publish.risk.label_risk_level')}
                            info={__('assistants.builder.publish.risk.description')}/>
                    </span>
                    <span class="tile-value" data-tone={ValidationState.SAFE}>
                        <span class="dot" aria-hidden="true"></span>{__('assistants.builder.publish.risk.risk_level_low')}
                    </span>
                </div>
                <div class="tile">
                    <span class="tile-label">
                        {__('assistants.builder.publish.completeness.label')}
                        <InfoPopover
                            label={__('assistants.builder.publish.completeness.label')}
                            info={__('assistants.builder.publish.completeness.description')}/>
                    </span>
                    <span class="tile-value" data-tone={builder.validator.isComplete ? ValidationState.SAFE : ValidationState.WARNING}>
                        <RadialProgress
                            class="ring"
                            value={progress * 100}
                            size={16}
                            aria-label={__('assistants.builder.publish.completeness.label')}/>
                        <span class="count">{completedCount}/{totalCount}</span>
                    </span>
                </div>
            </div>

            <ul class="checks">
                {#each builder.validator.completeness as item (item.id)}
                    {@render checkRow(item)}
                {/each}
            </ul>
        </section>

        <!--  ------------------------------------   -->

        {#if requiresReview}
            <section class="overview">
                <h4 class="section-title">{__('assistants.builder.publish.triggers.title')}</h4>
                <ul class="checks">
                    {#each builder.validator.triggers as item (item.id)}
                        {@render checkRow(item)}
                    {/each}
                </ul>
            </section>

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
                hint={__('assistants.builder.publish.input_version_note_hint')}
                assistantValueKey="submissionNote"
                />

        {/if}

    </div>
</div>

<style>
    /* Overview: flat surface tiles (same fill as the step footer bar) over
       one checklist surface; no outlines. */
    .overview {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }
    .tiles {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--space-2);
    }
    @media (--bp-xs-and-smaller) {
        .tiles {
            grid-template-columns: 1fr;
        }
    }
    .tile {
        display: flex;
        flex-direction: column;
        gap: var(--space-1_5);
        padding: var(--space-4);
        border-radius: var(--corner-md);
        background: var(--color-surface-light);
    }
    .tile-label {
        display: flex;
        align-items: center;
        gap: var(--space-1);
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    /* Tone drives the dot / ring / icon color. */
    [data-tone] { --tone: var(--color-text-muted); }
    [data-tone='info'] { --tone: var(--color-info); }
    [data-tone='safe'] { --tone: var(--color-success); }
    [data-tone='warning'] { --tone: color-mix(in oklch, var(--color-warning) 80%, var(--color-text)); }
    [data-tone='error'] { --tone: var(--color-error); }

    .tile-value {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-medium);
    }
    .dot {
        width: var(--space-2);
        height: var(--space-2);
        border-radius: var(--corner-full);
        background: var(--tone);
        transition: background-color var(--duration-fast) var(--easing-default);
    }
    .tile-value :global(.ring) {
        color: var(--tone);
        transition: color var(--duration-fast) var(--easing-default);
    }
    .count {
        font-variant-numeric: tabular-nums;
    }

    .section-title {
        margin: var(--space-2) 0 0;
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-medium);
    }

    .checks {
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0 var(--space-4);
        list-style: none;
        border-radius: var(--corner-md);
        background: var(--color-surface-light);
    }
    .check {
        display: flex;
        align-items: flex-start;
        gap: var(--space-3);
        padding: var(--space-3) 0;
        font-size: var(--font-size-sm);
    }
    .check + .check {
        border-top: var(--divider);
    }
    .check-icon {
        display: flex;
        align-items: center;
        flex-shrink: 0;
        height: calc(var(--font-size-sm) * var(--line-height-normal));
        font-size: var(--font-size-sm);
        color: var(--tone);
        transition: color var(--duration-fast) var(--easing-default);
    }
    .check-text {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-width: 0;
        line-height: var(--line-height-normal);
    }
    .check-description {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    .check-group {
        flex-shrink: 0;
        font-size: var(--font-size-xs);
        line-height: calc(var(--font-size-sm) * var(--line-height-normal));
        color: var(--color-text-muted);
    }
    @media (--bp-xs-and-smaller) {
        .check-group {
            display: none;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dot,
        .tile-value :global(.ring),
        .check-icon {
            transition: none;
        }
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
