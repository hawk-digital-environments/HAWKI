<!--
  @component Pinned footer of the builder flow: Back / Continue (step
  navigation itself lives in the sidebar). A navigation guard only lets the user move to steps whose
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
    const continueText = $derived(isLast ? '' : __('assistants.builder.steps.continue_to', {step: __(`assistants.builder.sidebar.${BUILDER_STEPS[index + 1]}`)}));
    let continueWidth = $state<number>();

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

    async function next() {
        // Land pending edits first: only the server knows whether the handle
        // is still free, and its verdict has to be in before moving on.
        await builder.flushSave();
        const rejected = builder.validator.hasRejectedField(current);
        if (!builder.validator.validateStep(current, __('assistants.builder.steps.required'))) {
            toast.error(__(rejected ? 'assistants.builder.steps.invalid' : 'assistants.builder.steps.incomplete'));
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
    // Per-step completeness and the furthest step opened feed the sidebar's
    // step checklist.
    $effect(() => {
        const complete: Partial<Record<BuilderStep, boolean>> = {};
        const filled: Partial<Record<BuilderStep, number>> = {};
        const total: Partial<Record<BuilderStep, number>> = {};
        for (const item of builder.validator.completeness) {
            if (!item.step) continue;
            complete[item.step] = (complete[item.step] ?? true) && item.ok;
            filled[item.step] = (filled[item.step] ?? 0) + (item.ok ? 1 : 0);
            total[item.step] = (total[item.step] ?? 0) + 1;
        }
        builderProgress.complete = complete;
        builderProgress.ratio = Object.fromEntries(
            BUILDER_STEPS.filter(step => total[step]).map(step => [step, filled[step]! / total[step]!])
        );
    });
    $effect(() => {
        if (index > builderProgress.furthest) builderProgress.furthest = index;
    });
    $effect(() => () => {
        builderProgress.reachable = BUILDER_STEPS.length;
        builderProgress.complete = {};
        builderProgress.ratio = {};
        builderProgress.furthest = 0;
    });

    // Only completed steps are navigable: a jump past the first incomplete
    // step (browser history, anything bypassing Continue) is vetoed and that step's missing
    // fields are marked. Going back is always allowed.
    $effect(() => {
        return router.registerNavigationGuard(async ({to}) => {
            const target = stepIndexOf(to);
            if (target === -1 || target <= index) return true;

            // Moving forward: see `next()` for why pending edits land first.
            await builder.flushSave();
            const blocker = builder.validator.firstIncompleteStep;
            if (target <= blocker) return true;

            const rejected = builder.validator.hasRejectedField(BUILDER_STEPS[blocker]);
            if (blocker === index) {
                builder.validator.validateStep(current, __('assistants.builder.steps.required'));
            }
            toast.error(__(rejected ? 'assistants.builder.steps.invalid' : 'assistants.builder.steps.incomplete'));
            return false;
        });
    });
</script>

<footer class="step-footer">
  <div class="bar">
    <div class="back">
        {#if index > 0}
            <Button class="press" variant="stroke" iconLeft={ArrowLeft01Icon}
                    aria-label={__('assistants.builder.steps.back')} title={__('assistants.builder.steps.back')}
                    onclick={() => goTo(BUILDER_STEPS[index - 1])}/>
        {/if}
    </div>

    <div class="actions">
        {#if !isLast}
            <Button class="icon-end brand press" variant="accent" iconRight={ArrowRight01Icon} onclick={next}>
                <!-- The slot eases to the new label's width; the keyed label fades in. -->
                <span class="continue-label" style:width={continueWidth === undefined ? undefined : `${continueWidth}px`}>
                    {#key continueText}
                        <span class="continue-text" bind:offsetWidth={continueWidth}>{continueText}</span>
                    {/key}
                </span>
            </Button>
        {:else if canRelease}
            <Button class="icon-start brand press" variant="accent" iconLeft={FloppyDiskIcon} onclick={() => builder.requestRelease()}>
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
        --footer-pad-x: var(--space-8);
        /* Bottom inset matches the test chat launcher's, which shares this
           row (see layout.svelte). */
        padding: var(--space-6) var(--footer-pad-x) var(--space-4);
        pointer-events: none;
    }

    .step-footer::before {
        content: '';
        position: absolute;
        z-index: -1;
        /* Reaches well above the bar so the fade has room to ease out. */
        inset: -3rem 0 0;
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

    /* Aligned with the section page's content column (.page-content), but
       never reaching into `--step-footer-end-reserve` (the host's launcher
       corner). No surface of its own: the blurred fade behind it already
       sets it apart. */
    .bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--space-4);
        max-width: calc(48rem - 2 * var(--space-8));
        margin-left: auto;
        margin-right: max(
            0px,
            calc(var(--step-footer-end-reserve, 0px) - var(--footer-pad-x)),
            calc((100% - (48rem - 2 * var(--space-8))) / 2)
        );
        pointer-events: auto;
    }

    /* Round Back button, same height as Continue. */
    .back :global(.btn) {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: var(--corner-full);
    }

    .actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }

    /* A short press-down gives the step buttons a tactile click. */
    .bar :global(.btn.press) {
        transition: filter var(--duration-fast), background-color var(--duration-fast), scale var(--duration-extra-fast) var(--easing-out);
    }
    .bar :global(.btn.press:active) {
        scale: 0.96;
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

    /* Primary step action: the brand ramp of the sidebar's create button
       (SidebarButton), deepening on hover instead of a flat overlay. */
    .actions :global(.btn.brand) {
        background: linear-gradient(
            135deg,
            var(--gradient-brand-1),
            var(--gradient-brand-2) 55%,
            var(--gradient-brand-3)
        );
        /* Paint under the button's transparent border too; otherwise the ramp
           tiles into the 1px border and shows a seam at the edges. */
        background-origin: border-box;
        color: var(--color-on-accent-fill);
    }
    .actions :global(.btn.brand:hover) {
        filter: brightness(0.92) saturate(1.08);
    }

    .continue-label {
        display: inline-block;
        overflow: hidden;
        white-space: nowrap;
        transition: width var(--duration-fast) var(--easing-out);
    }
    .continue-text {
        display: inline-block;
        width: max-content;
        animation: continue-in var(--duration-fast) var(--easing-out);
    }
    @keyframes continue-in {
        from { opacity: 0; }
    }
    @media (prefers-reduced-motion: reduce) {
        .bar :global(.btn.press:active) { scale: none; }
        .continue-label { transition: none; }
        .continue-text { animation: none; }
    }

    @media (--bp-md-and-smaller) {
        .step-footer {
            --footer-pad-x: var(--space-4);
        }
    }
</style>
