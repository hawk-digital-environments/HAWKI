<!--
  @component Pinned footer of the review detail's step flow: Back / Continue
  between the Changes and Review steps. The final step carries the primary
  accent action — Approve — where the builder's publish step keeps its
  release action; the secondary decisions and the reason field live in the
  Review step's content.
-->
<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import CheckmarkBadge01Icon from '$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useReviewDetailContext} from '$plugins/assistants/admin/contexts/reviewDetailContext.svelte.js';

    const REVIEW_STEPS = ['changes', 'review'] as const;
    type ReviewStep = typeof REVIEW_STEPS[number];

    const router = useRouter();
    const {__} = useTranslator();
    const review = useReviewDetailContext();

    const index = $derived(Math.max(0, REVIEW_STEPS.findIndex(step => router.isRouteActive(`admin.assistants.${step}`))));
    const isLast = $derived(index === REVIEW_STEPS.length - 1);
    const assistantId = $derived(review.target.value?.id);

    /**
     * Any review row is decidable — pending or already decided: re-deciding
     * runs the same transitions (deny after approval revokes, re-approval
     * clears a denial). The builder's canRelease gate.
     */
    const canDecide = $derived(review.target.value?.assistant_review != null);

    function goTo(step: ReviewStep): void {
        if (!assistantId) return;
        void router.goToRoute(`admin.assistants.${step}`, {assistantId});
    }
</script>

<footer class="step-footer">
    <div class="bar">
        <div class="back">
            {#if index > 0}
                <Button
                    class="press"
                    variant="stroke"
                    iconLeft={ArrowLeft01Icon}
                    aria-label={__('assistants.admin.steps.back')}
                    title={__('assistants.admin.steps.back')}
                    onclick={() => goTo(REVIEW_STEPS[index - 1])}
                />
            {/if}
        </div>
        <div class="actions">
            {#if !isLast}
                <Button class="icon-end brand press" variant="accent" iconRight={ArrowRight01Icon} onclick={() => goTo(REVIEW_STEPS[index + 1])}>
                    {__('assistants.admin.steps.continue_to', {step: __(`assistants.admin.steps.${REVIEW_STEPS[index + 1]}`)})}
                </Button>
            {:else if canDecide}
                <Button
                    class="icon-start brand press"
                    variant="accent"
                    iconLeft={CheckmarkBadge01Icon}
                    disabled={review.deciding || null === review.selectedDecision}
                    onclick={() => void review.submit()}
                >
                    {__('assistants.admin.steps.submit')}
                </Button>
            {/if}
        </div>
    </div>
</footer>

<style>
    .step-footer {
        position: fixed;
        inset-inline: 0;
        bottom: 0;
        z-index: 10;
        pointer-events: none;
        padding: var(--space-4) var(--space-8);
    }

    .bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-4);
        max-width: 64rem;
        margin: 0 auto;
        pointer-events: auto;
    }
</style>
