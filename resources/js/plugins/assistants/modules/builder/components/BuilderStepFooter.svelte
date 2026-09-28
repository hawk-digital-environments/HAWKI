<!--
  @component Pinned footer of the builder flow: step progress plus Back /
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
    <ol class="steps" aria-label={__('assistants.builder.steps.progress')}>
        {#each BUILDER_STEPS as step, i (step)}
            <li>
                <button
                        type="button"
                        class="step"
                        class:done={i < index}
                        class:active={i === index}
                        aria-current={i === index ? 'step' : undefined}
                        onclick={() => goTo(step)}
                >
                    <span class="bar"></span>
                    <span class="label">{__(`assistants.builder.sidebar.${step}`)}</span>
                </button>
            </li>
        {/each}
    </ol>

    <div class="actions">
        <Button variant="ghost" iconLeft={ArrowLeft01Icon} disabled={index === 0}
                onclick={() => goTo(BUILDER_STEPS[index - 1])}>
            {__('assistants.builder.steps.back')}
        </Button>
        {#if !isLast}
            <Button variant="accent" iconRight={ArrowRight01Icon} onclick={next}>
                {__('assistants.builder.steps.continue')}
            </Button>
        {/if}
    </div>
</footer>

<style>
    .step-footer {
        display: flex;
        align-items: center;
        gap: var(--space-5);
        padding: var(--space-3) var(--space-8);
        border-top: 1px solid var(--color-border);
        background: var(--color-bg);
    }

    .steps {
        display: flex;
        flex: 1;
        gap: var(--space-2);
        margin: 0;
        padding: 0;
        list-style: none;
        min-width: 0;
    }

    .steps li {
        flex: 1;
        min-width: 0;
    }

    .step {
        display: flex;
        flex-direction: column;
        gap: var(--space-1_5);
        width: 100%;
        padding: 0;
        border: 0;
        background: none;
        text-align: left;
        cursor: pointer;
    }


    .bar {
        height: 4px;
        border-radius: 2px;
        background: var(--color-border);
    }

    .step.done .bar,
    .step.active .bar {
        background: var(--color-accent-fill);
    }

    .label {
        overflow: hidden;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .step.active .label {
        color: var(--color-text);
    }

    .actions {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }

    @media (--bp-md-and-smaller) {
        .step-footer {
            flex-direction: column;
            align-items: stretch;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
        }

        .label {
            display: none;
        }

        .actions {
            justify-content: flex-end;
        }
    }
</style>
