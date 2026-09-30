<!--
  @component Step progress as a row of pills: completed steps fill in, the
  current one stretches with a springy overshoot. Purely visual; `label`
  carries the "Step x of y" text for assistive tech.

  @example
  ```svelte
  <StepDots current={1} total={5} label={__('…step_of', …)}/>
  ```
-->
<script lang="ts">
    interface Props {
        /** Zero-based index of the current step. */
        current: number;
        /** Number of steps. */
        total: number;
        /** Accessible description of the progress, e.g. "Step 2 of 5". */
        label: string;
        class?: string;
    }

    const {current, total, label, class: className}: Props = $props();
</script>

<ol class={['step-dots', className]} aria-label={label}>
    {#each {length: total}, i (i)}
        <li class="step-dot" class:done={i < current} class:active={i === current} aria-current={i === current ? 'step' : undefined}></li>
    {/each}
</ol>

<style>
    .step-dots {
        --step-dot-track: var(--color-border);
        --step-dot-fill: var(--color-accent-fill);
        display: flex;
        gap: var(--space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    /* Dark mode: the border token sits within a few % of raised surfaces and
       the accent fill is near-black, so the pills would vanish. A translucent
       text-tinted track reads on any surface; the fill uses the light accent. */
    :global(html.darkMode) .step-dots {
        --step-dot-track: color-mix(in oklab, var(--color-text) 22%, transparent);
        --step-dot-fill: var(--color-accent-text);
    }
    .step-dot {
        position: relative;
        overflow: hidden;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 999px;
        background: var(--step-dot-track);
        transition: width var(--duration-fast) var(--easing-spring);
    }
    .step-dot::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: var(--step-dot-fill);
        transform: translateX(-100%);
        transition: transform var(--duration-extra-fast) var(--easing-out);
    }
    .step-dot.active {
        width: 1.75rem;
    }
    .step-dot.done::after,
    .step-dot.active::after {
        transform: none;
    }
    .step-dot.done {
        animation: step-dot-pop var(--duration-extra-fast) var(--easing-spring);
    }
    @keyframes step-dot-pop {
        50% { scale: 1.4; }
    }
</style>
