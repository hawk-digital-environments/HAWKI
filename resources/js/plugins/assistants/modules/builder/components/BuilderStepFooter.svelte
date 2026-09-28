<!--
  @component Pinned footer of the builder flow: the current step's position (step
  navigation itself lives in the sidebar) plus Back /
  Continue. A navigation guard only lets the user move to steps whose
  predecessors are complete; a blocked jump marks the missing fields inline
  (`validator.validateStep`) and shows an error toast. On the last step (publish) Continue is replaced by
  the release action.
-->
<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import FloppyDiskIcon from '$lib/components/ui/icons/iconset/FloppyDiskIcon.svelte';
    import {ReleaseMode} from '$plugins/assistants/types/assistant/ReleaseMode';
    import {ReviewStage} from '$plugins/assistants/types/assistant/ReviewStage';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useBuilderContext} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import {builderProgress} from '$plugins/assistants/modules/builder/contexts/builderProgress.svelte.js';
    import {BUILDER_STEPS, type BuilderStep} from '$plugins/assistants/modules/builder/contexts/BuilderValidatorContext.svelte.js';

    const builder = useBuilderContext();
    const router = useRouter();
    const {__} = useTranslator();
    const toast = useToastContext();

    const routeOf = (step: BuilderStep) => `assistants.builder.${step}`;

    const index = $derived(Math.max(0, BUILDER_STEPS.findIndex(step => router.isRouteActive(routeOf(step)))));
    const current = $derived(BUILDER_STEPS[index]);
    const isLast = $derived(index === BUILDER_STEPS.length - 1);

    // Last step: the release action (moved here from the publish page). A
    // permanently denied assistant can't be resubmitted, so no action then.
    const canRelease = $derived(builder.draft.review?.status !== ReviewStage.DENIED);
    const releaseText = $derived.by(() => {
        switch (builder.draft.releaseStage) {
            case ReleaseMode.PRIVATE:
                return __('assistants.builder.publish.save_as_private_assistant');
            case ReleaseMode.ORGANIZATIONAL:
            case ReleaseMode.FEDERATED:
                return __('assistants.builder.publish.save_as_review');
            default:
                return __('assistants.builder.publish.keep_as_draft');
        }
    });

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

    /** Builder step a path points at, or -1 for non-step paths. */
    const basePath = router.getPath('assistants.builder.index').replace(/\/+$/, '');
    function stepIndexOf(path: string): number {
        if (!path.startsWith(basePath + '/')) return -1;
        const segment = path.slice(basePath.length + 1).split(/[/?#]/)[0];
        return BUILDER_STEPS.indexOf(segment as BuilderStep);
    }

    // Publish how far the flow may be navigated, so the sidebar can disable
    // locked steps; reset when the builder unmounts.
    $effect(() => {
        builderProgress.reachable = builder.validator.firstIncompleteStep;
    });
    $effect(() => () => { builderProgress.reachable = BUILDER_STEPS.length; });

    // Only completed steps are navigable: a jump past the first incomplete
    // step (browser history, anything bypassing Continue) is vetoed and that step's missing
    // fields are marked. Going back is always allowed.
    $effect(() => {
        return router.registerNavigationGuard(({to}) => {
            const target = stepIndexOf(to);
            const blocker = builder.validator.firstIncompleteStep;
            if (target === -1 || target <= blocker) return true;

            if (blocker === index) {
                builder.validator.validateStep(current, __('assistants.builder.steps.required'));
            }
            toast.error(__('assistants.builder.steps.incomplete'));
            return false;
        });
    });
</script>

<footer class="step-footer">
  <div class="bar">
    <div class="progress">
        <span class="count">
            {__('assistants.builder.steps.step_of', {current: String(index + 1), total: String(BUILDER_STEPS.length)})}
        </span>
    </div>

    <div class="actions">
        {#if index > 0}
            <Button class="icon-start" variant="ghost" iconLeft={ArrowLeft01Icon}
                    onclick={() => goTo(BUILDER_STEPS[index - 1])}>
                {__('assistants.builder.steps.back')}
            </Button>
        {/if}
        {#if !isLast}
            <Button class="icon-end" variant="accent" iconRight={ArrowRight01Icon} onclick={next}>
                {__('assistants.builder.steps.continue_to', {step: __(`assistants.builder.sidebar.${BUILDER_STEPS[index + 1]}`)})}
            </Button>
        {:else if canRelease}
            <Button class="icon-start" variant="accent" iconLeft={FloppyDiskIcon} onclick={() => builder.requestRelease()}>
                {releaseText}
            </Button>
        {/if}
    </div>
  </div>
</footer>

<style>
    /* Floats over the bottom of the scrolling content (see layout.svelte);
       the content fades out under a blurred backdrop instead of being cut
       off. Same eased-ramp technique as PageHeaderBar, mirrored. */
    .step-footer {
        position: relative;
        box-sizing: border-box;
        width: 100%;
        padding: var(--space-6) var(--space-8) var(--space-4);
        pointer-events: none;
    }

    .step-footer::before {
        content: '';
        position: absolute;
        z-index: -1;
        inset: 0;
        background: color-mix(in oklch, var(--color-bg) 88%, transparent);
        backdrop-filter: blur(12px);
        --footer-fade: linear-gradient(
            to top,
            black 0,
            black 45%,
            rgba(0, 0, 0, 0.86) 60%,
            rgba(0, 0, 0, 0.55) 72%,
            rgba(0, 0, 0, 0.25) 84%,
            rgba(0, 0, 0, 0.08) 92%,
            transparent 100%
        );
        mask-image: var(--footer-fade);
        -webkit-mask-image: var(--footer-fade);
    }

    /* Aligned with the section page's content column (.page-content). */
    .bar {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        max-width: calc(48rem - 2 * var(--space-8));
        margin: 0 auto;
        padding: var(--space-1_5) var(--space-1_5) var(--space-1_5) var(--space-5);
        border-radius: var(--corner-full);
        background: var(--color-surface-light);
        pointer-events: auto;
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

    /* Optical balance: tighter icon gap, and less padding on the icon side
       (a glyph carries its own whitespace, unlike a text edge). */
    .actions :global(.btn) {
        column-gap: var(--space-1_5);
    }
    .actions :global(.btn.icon-start) {
        padding-left: var(--space-3);
    }
    .actions :global(.btn.icon-end) {
        padding-right: var(--space-3);
    }

    @media (--bp-md-and-smaller) {
        .step-footer {
            padding: var(--space-6) var(--space-4) var(--space-3);
        }
    }
</style>
