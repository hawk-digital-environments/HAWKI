<!--
  @component Review step 2 "Review": the decision surface, styled like the
  builder's publish step — the decision as a card radio group ("Choose the
  publishing path" pattern: icon, label, description per option) with the
  reason as a builder-style field. An already decided review shows its
  previous decision as an info box above the cards and stays decidable:
  re-deciding runs the same transitions (deny after approval revokes the
  publication, re-approval clears a denial). The final Submit action lives
  in the step footer, where publish keeps its release action.
-->
<script lang="ts">
    import type {RouteProps} from '$lib/components/ui/routing/index.js';
    const {}: RouteProps = $props();
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import Alert from '$lib/components/ui/alert/Alert.svelte';
    import CheckmarkBadge01Icon from '$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte';
    import TaskEdit01Icon from '$lib/components/ui/icons/iconset/TaskEdit01Icon.svelte';
    import AlertCircleIcon from '$lib/components/ui/icons/iconset/AlertCircleIcon.svelte';
    import RadioSwitch from '$plugins/assistants/components/radioSwitch/RadioSwitch.svelte';
    import RadioOption from '$plugins/assistants/components/radioSwitch/RadioOption.svelte';
    import {useReviewDetailContext} from '$plugins/assistants/admin/contexts/reviewDetailContext.svelte.js';
    import type {ReviewStatus} from '$plugins/assistants/admin/api/schemas.js';
    import {reviewAlertTone} from '$plugins/assistants/components/status/reviewTones.js';
    import {formatDate} from '$lib/utils/date.js';

    const {__} = useTranslator();
    const review = useReviewDetailContext();

    let reasonEl = $state<HTMLTextAreaElement | null>(null);

    const target = $derived(review.target.value);
    const targetReview = $derived(target?.assistant_review ?? null);

    /** Info-box tone for the previous decision (Alert has no "safe"). */
    const PREVIOUS_ICON: Record<ReviewStatus, typeof TaskEdit01Icon> = {
        pending: TaskEdit01Icon,
        approved: CheckmarkBadge01Icon,
        needs_revision: TaskEdit01Icon,
        denied: AlertCircleIcon
    };

    const DECISIONS = [
        {value: 'approved', icon: CheckmarkBadge01Icon},
        {value: 'needs_revision', icon: TaskEdit01Icon},
        {value: 'denied', icon: AlertCircleIcon}
    ] as const;

    /** "Approved on Nov 9, 2026" — the decided state names status and date. */
    const decidedText = $derived.by(() => {
        if (!targetReview) return '';
        const stamp = targetReview.reviewed_at ?? targetReview.updated_at;
        if (!stamp) return __(`assistants.admin.queue.status.${targetReview.status}`);

        return __(`assistants.admin.detail.decided_${targetReview.status}`, {
            date: formatDate(stamp)
        });
    });

    /** "Reason" vs "Reason *" — the mark follows the picked decision card. */
    const reasonRequired = $derived(review.selectedDecision === 'denied' || review.selectedDecision === 'needs_revision');

    // When the required error appears, the field takes focus so the fix is
    // obvious (typing or picking Approve clears it via the handlers).
    $effect(() => {
        if (review.reasonError) reasonEl?.focus();
    });
</script>

{#if targetReview && targetReview.status !== 'pending'}
    <Alert
        size="small"
        tone={reviewAlertTone(targetReview.status)}
        icon={PREVIOUS_ICON[targetReview.status]}
        title={decidedText}
        description={targetReview.reason
            ? `${__('assistants.admin.detail.previous_reason')}: ${targetReview.reason}`
            : undefined}
    />
{/if}

<div class="input-container">
    <p class="u-label">{__('assistants.admin.detail.decision_title')}</p>
    <RadioSwitch
        value={review.selectedDecision}
        onchange={(value) => {
            review.selectedDecision = value as typeof review.selectedDecision;
            review.reasonError = false;
        }}
        name="review-decision"
    >
        {#each DECISIONS as decision (decision.value)}
            {@const lift = decision.value === 'needs_revision' && targetReview?.status === 'denied'}
            <RadioOption
                value={decision.value}
                icon={decision.icon}
                label={__(`assistants.admin.detail.decision_${decision.value}_label`)}
                description={__(lift
                    ? 'assistants.admin.detail.decision_needs_revision_lift_description'
                    : `assistants.admin.detail.decision_${decision.value}_description`)}
                disabled={review.deciding}
            />
        {/each}
    </RadioSwitch>
</div>

<div class="field">
    <label class="u-label" for="review-reason">
        {__('assistants.admin.detail.reason')}{#if reasonRequired}&nbsp;*{/if}
    </label>
    <Textarea
        id="review-reason"
        bind:ref={reasonEl}
        value={review.reason}
        oninput={(event) => review.setReason(event.currentTarget.value)}
        rows={2}
        disabled={review.deciding}
        placeholder={__('assistants.admin.detail.reason_placeholder')}
        aria-required={reasonRequired}
        aria-invalid={review.reasonError}
        aria-describedby={review.reasonError ? 'review-reason-error' : undefined}
    />
    {#if review.reasonError}
        <p class="field-error" id="review-reason-error" role="alert">
            {__('assistants.admin.detail.field_required')}
        </p>
    {/if}
</div>

<style>
    .input-container {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .field {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
    }

    .field-error {
        margin: 0;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        color: var(--color-error);
    }
</style>
