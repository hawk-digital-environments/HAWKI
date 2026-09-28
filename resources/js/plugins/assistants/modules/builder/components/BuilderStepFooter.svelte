<!--
  @component Pinned footer of the builder flow: the current step's position (step
  navigation itself lives in the sidebar) plus Back /
  Continue. "Continue" checks the current step's required fields first
  (`validator.validateStep`), marking empty ones inline and staying put if
  any are missing (with an error toast). The last step (publish) has its own action, so no
  Continue there.
-->
<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import {BUILDER_STEPS, type BuilderStep} from '$plugins/assistants/modules/builder/contexts/BuilderValidatorContext.svelte.js';

    const builder = useBuilderContext();
    const router = useRouter();
    const {__} = useTranslator();
    const toast = useToastContext();

    const routeOf = (step: BuilderStep) => `assistants.builder.${step}`;

    const index = $derived(Math.max(0, BUILDER_STEPS.findIndex(step => router.isRouteActive(routeOf(step)))));
    const current = $derived(BUILDER_STEPS[index]);
    const isLast = $derived(index === BUILDER_STEPS.length - 1);

    function goTo(step: BuilderStep) {
        void router.goToRoute(routeOf(step));
    }

    function next() {
        if (!builder.validator.validateStep(current, __('assistants.builder.steps.required'))) {
            toast.error(__('assistants.builder.steps.incomplete'));
            return;
        }
        goTo(BUILDER_STEPS[index + 1]);
    }
</script>

<footer class="step-footer">
  <div class="bar">
    <div class="progress">
        <span class="count">
            {__('assistants.builder.steps.step_of', {current: String(index + 1), total: String(BUILDER_STEPS.length)})}
        </span>
    </div>

    <div class="actions">
        <Button variant="ghost" iconLeft={ArrowLeft01Icon} disabled={index === 0}
                onclick={() => goTo(BUILDER_STEPS[index - 1])}>
            {__('assistants.builder.steps.back')}
        </Button>
        {#if !isLast}
            <Button variant="accent" iconRight={ArrowRight01Icon} onclick={next}>
                {__('assistants.builder.steps.continue_to', {step: __(`assistants.builder.sidebar.${BUILDER_STEPS[index + 1]}`)})}
            </Button>
        {/if}
    </div>
  </div>
</footer>

<style>
    /* Aligned with the section page's content column (.page-content). */
    .step-footer {
        box-sizing: border-box;
        width: 100%;
        max-width: 48rem;
        margin: 0 auto;
        padding: var(--space-2) var(--space-8) var(--space-4);
    }

    .bar {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        padding: var(--space-1_5) var(--space-1_5) var(--space-1_5) var(--space-5);
        border-radius: var(--corner-full);
        background: var(--color-surface-light);
    }

    .progress {
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
        min-width: 0;
    }

    .count {
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
        font-variant-numeric: tabular-nums;
    }

    .actions {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: var(--space-1);
        margin-left: auto;
    }

    @media (--bp-md-and-smaller) {
        .step-footer {
            padding: var(--space-2) var(--space-4) var(--space-3);
        }
    }
</style>
