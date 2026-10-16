<!--
  @component A "still working" status that steps through a sequence of stages,
  each an icon and a shimmering label (e.g. reading → thinking → writing).
  Stages advance on a timer (`at`, ms after mount) and the last one holds
  until the component is removed, so use it for work that reports no progress
  of its own and phrase every stage so it is true for the whole wait.

  Stages swap with a short slide + blur: the old one moves up and fades out,
  the new one rises in from below. Under reduced motion they swap instantly.

  @example
  ```svelte
  <StatusSteps steps={[
      {icon: MessageSearch01Icon, label: __('…reading'), at: 0},
      {icon: AiBrain01Icon, label: __('…thinking'), at: 1500},
  ]}/>
  ```
-->
<script module lang="ts">
    import type {IconComponent} from '$lib/components/ui/icons';

    export interface StatusStep {
        icon: IconComponent;
        label: string;
        /** When this stage starts, in ms after mount. */
        at: number;
    }
</script>

<script lang="ts">
    import {onDestroy, tick} from 'svelte';
    import ShimmerText from '$lib/components/ui/shimmer-text/ShimmerText.svelte';
    import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte.js';

    interface Props {
        steps: StatusStep[];
    }

    const {steps}: Props = $props();

    /** Matches `--swap-dur` below. */
    const SWAP_MS = 150;

    const reducedMotion = useReducedMotion();
    let index = $state(0);
    let phase = $state<'idle' | 'exit' | 'enter-start'>('idle');
    let el = $state<HTMLElement | null>(null);
    const timers: ReturnType<typeof setTimeout>[] = [];

    const current = $derived(steps[Math.min(index, steps.length - 1)]);

    async function advance(next: number): Promise<void> {
        if (reducedMotion.current) {
            index = next;
            return;
        }
        // 1. Old stage slides up, blurs and fades.
        phase = 'exit';
        await new Promise((resolve) => timers.push(setTimeout(resolve, SWAP_MS)));
        // 2. Swap it and jump below, without a transition…
        index = next;
        phase = 'enter-start';
        await tick();
        // 3. …then let it rise back into place.
        void el?.offsetHeight;
        phase = 'idle';
    }

    // Stages are fixed for the lifetime of one wait.
    // svelte-ignore state_referenced_locally
    steps.forEach((step, i) => {
        if (i > 0) timers.push(setTimeout(() => void advance(i), step.at));
    });

    onDestroy(() => timers.forEach(clearTimeout));
</script>

<span class="status-steps" role="status" aria-live="polite">
    <span bind:this={el} class="stage" class:is-exit={phase === 'exit'} class:is-enter-start={phase === 'enter-start'}>
        <span class="icon" aria-hidden="true"><current.icon size="1.1em"/></span>
        <ShimmerText>{current.label}</ShimmerText>
    </span>
</span>

<style>
    /* Transition values from Transitions.dev's "Text states swap". */
    .status-steps {
        --swap-dur: 150ms;
        --swap-translate-y: 4px;
        --swap-blur: 2px;
        --swap-ease: ease-in-out;
        display: inline-flex;
    }

    .stage {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1_5);
        opacity: 1;
        /* No resting transform/filter and no will-change: a filter layer on
           an ancestor keeps Chrome from painting the label's
           `background-clip: text` shimmer. They only apply mid-swap. */
        transition:
            transform var(--swap-dur) var(--swap-ease),
            filter var(--swap-dur) var(--swap-ease),
            opacity var(--swap-dur) var(--swap-ease);
    }

    .stage.is-exit {
        transform: translateY(calc(var(--swap-translate-y) * -1));
        filter: blur(var(--swap-blur));
        opacity: 0;
    }

    .stage.is-enter-start {
        transform: translateY(var(--swap-translate-y));
        filter: blur(var(--swap-blur));
        opacity: 0;
        transition: none;
    }

    /* Same tone as the shimmer's resting colour, so icon and label read as one. */
    .icon {
        display: inline-flex;
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    @media (prefers-reduced-motion: reduce) {
        .stage {
            transition: none;
        }
    }
</style>
