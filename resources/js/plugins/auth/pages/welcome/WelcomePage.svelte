<!--
  @component One onboarding step (heading, body, navigation), rendered for every
  step route of the nested router (see `RegistrationWelcome.svelte`). Which step
  it shows comes from the route meta; the texts come as one bundle from
  `ui.auth.register.welcome.<step>` (`title`, `body`, `action`).

  Back leads to the previous step; the action button leads to the next step or,
  on the last step, finishes the onboarding. The nested router keeps this
  component mounted across steps and only swaps the meta, so after an in-flow
  navigation an effect moves focus to the new heading (the change is announced),
  and the markup is keyed on the step so the entry fade replays.
-->
<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import {useRouter, type RouteProps} from '$lib/components/ui/routing/index.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {WelcomeStepMeta} from './welcomeRoutes.js';
    import {useWelcomeFlow} from './welcomeFlow.js';

    const {meta}: RouteProps<void, void, WelcomeStepMeta> = $props();
    const router = useRouter();
    const flow = useWelcomeFlow();
    const {__, getTranslations} = useTranslator();
    let heading = $state<HTMLHeadingElement | null>(null);
    /** Measured height of the content; the outer box follows it so step changes resize smoothly. */
    let contentHeight = $state<number | undefined>(undefined);

    interface StepTexts {
        title: string;
        body: string;
        action: string;
    }
    const texts = $derived.by((): StepTexts => {
        const bundle = getTranslations(`ui.auth.register.welcome.${meta.step}`);
        const text = (key: keyof StepTexts) =>
            bundle && typeof bundle === 'object' && typeof bundle[key] === 'string' ? bundle[key] : `Missing translation: ui.auth.register.welcome.${meta.step}.${key}`;
        return {title: text('title'), body: text('body'), action: text('action')};
    });

    // The router keeps this component mounted across steps (same page, new
    // meta), so the heading is focused from an effect on the step, not on mount.
    $effect(() => {
        void meta.step;
        if (flow.consumeFocusRequest()) heading?.focus();
    });

    function go(routeName: string) {
        flow.requestFocus();
        void router.goToRoute(routeName);
    }
</script>

<!-- Reserves the height of the longest step so the buttons stay in place while the text changes.
     Keyed on the step so each step re-enters with its fade, even though the component instance stays. -->
<!-- Wrapped in a box that animates to the measured content height, so a step with more text grows smoothly. -->
<div class="welcome-size" style:height={contentHeight === undefined ? undefined : `${contentHeight}px`}>
<div class="welcome" bind:offsetHeight={contentHeight}>
    <ol class="welcome-progress" aria-label={__('ui.auth.register.welcome.progress', {current: String(meta.index + 1), total: String(meta.total)})}>
        {#each {length: meta.total}, i (i)}
            <li class="welcome-dot" class:done={i < meta.index} class:active={i === meta.index} aria-current={i === meta.index ? 'step' : undefined}></li>
        {/each}
    </ol>
    {#key meta.step}
        <div class="auth-intro welcome-slide">
            <h1 id="auth-title" tabindex="-1" bind:this={heading}>{texts.title}</h1>
            <p class="auth-copy">{texts.body}</p>
        </div>
        <div class="welcome-actions">
            {#if meta.previous}
                <Button type="button" variant="ghost" iconLeft={ArrowLeft01Icon} onclick={() => go(meta.previous!)}>{__('ui.auth.register.welcome.back')}</Button>
            {/if}
            <Button type="button" variant="accent" onclick={() => meta.next ? go(meta.next) : flow.finish()}>{texts.action}</Button>
        </div>
    {/key}
</div>
</div>

<style>
    .welcome-size {
        overflow: hidden;
        transition: height var(--duration-fast) var(--easing-out);
    }
    .welcome {
        display: grid;
        grid-template-rows: auto 1fr auto;
        gap: var(--space-6);
        min-height: 18rem;
    }
    /* The intro fills the reserved row; keep heading and body together at its top. */
    .welcome :global(.auth-intro) {
        align-content: start;
    }
    /* Steps as pills: done ones fill in, the current one stretches with a springy overshoot. */
    .welcome-progress {
        display: flex;
        gap: var(--space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .welcome-dot {
        position: relative;
        overflow: hidden;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 999px;
        background: var(--color-border);
        transition: width var(--duration-fast) var(--easing-spring);
    }
    .welcome-dot::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: var(--color-accent-fill);
        transform: translateX(-100%);
        transition: transform var(--duration-extra-fast) var(--easing-out);
    }
    .welcome-dot.active {
        width: 1.75rem;
    }
    .welcome-dot.done::after,
    .welcome-dot.active::after {
        transform: none;
    }
    .welcome-dot.done {
        animation: welcome-pop var(--duration-extra-fast) var(--easing-spring);
    }
    @keyframes welcome-pop {
        50% { scale: 1.4; }
    }
    .welcome-slide {
        animation: welcome-fade var(--duration-medium) var(--easing-out) both;
    }
    .welcome-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: var(--space-2);
    }
    @keyframes welcome-fade {
        from { opacity: 0; transform: translateY(0.25rem); }
        to { opacity: 1; transform: none; }
    }
    /* On phones the onboarding fills the frame's main area, so the buttons sit at
       the bottom within thumb reach (above the frame footer). The measured height
       box steps aside here; the full-height layout doesn't need it. */
    @media (--bp-xs) {
        :global(.auth-main:has(.welcome)) {
            align-content: stretch;
        }
        /* The router's loader wraps the page in `.loader-host > .loader-content`; stretch those too. */
        :global(.auth-body:has(.welcome)),
        :global(.loader-host:has(.welcome)),
        :global(.loader-content:has(.welcome)) {
            display: flex;
            flex-direction: column;
        }
        :global(.loader-host:has(.welcome)),
        :global(.loader-content:has(.welcome)) {
            flex: 1;
        }
        .welcome-size {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: auto !important;
        }
        .welcome {
            flex: 1;
        }
        /* Back and the primary action sit at opposite edges, neither wrapping; alone, the action spans the row. */
        .welcome-actions {
            flex-wrap: nowrap;
            justify-content: space-between;
        }
        .welcome-actions > :global(*) {
            white-space: nowrap;
        }
        /* Tighter ghost (Back) button so its text lines up closer to the content edge. */
        .welcome .welcome-actions > :global(.btn.btn--ghost) {
            min-width: 0;
            padding-inline: var(--space-2);
        }
        .welcome-actions > :global(:only-child) {
            flex: 1;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .welcome-slide,
        .welcome-dot {
            animation: none;
        }
        .welcome-size,
        .welcome-dot,
        .welcome-dot::after {
            transition: none;
        }
    }
</style>
