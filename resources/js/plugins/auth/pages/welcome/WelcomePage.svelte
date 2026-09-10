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
<div class="welcome">
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

<style>
    .welcome {
        display: grid;
        grid-template-rows: 1fr auto;
        gap: var(--space-6);
        min-height: 18rem;
    }
    /* The intro fills the reserved row; keep heading and body together at its top. */
    .welcome :global(.auth-intro) {
        align-content: start;
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
    @media (prefers-reduced-motion: reduce) {
        .welcome-slide {
            animation: none;
        }
    }
</style>
