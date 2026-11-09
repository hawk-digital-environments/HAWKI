<!--
  @component The Admin Panel's module sidebar: one row per visible section,
  collected from every module that declares admin sections (see `registry.ts`).
  While a review detail flow is open, the list swaps for its two steps
  (Changes, Review) — the builder's connected wizard list: numbered rings
  joined by a connector that fills once a step is done.
-->
<script lang="ts">
    import SidebarItems from '$lib/components/ui/sidebar/SidebarItems.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import StepRing from '$lib/components/ui/step-ring/StepRing.svelte';
    import {adminRegistry} from '$plugins/admin/registry.js';
    import {currentReview} from '$plugins/assistants/admin/contexts/reviewDetailContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';

    const {__} = useTranslator();
    const router = useRouter();

    // The section gates read reactive state (the assistants section's
    // permission probe), so this derived re-runs when a verdict lands.
    const sections = $derived(adminRegistry.visibleSections);

    const STEPS = [
        {route: 'admin.assistants.changes', labelKey: 'assistants.admin.steps.changes'},
        {route: 'admin.assistants.review', labelKey: 'assistants.admin.steps.review'}
    ];

    const inDetailFlow = $derived(STEPS.some(step => router.isRouteActive(step.route)));
    const currentStep = $derived(STEPS.findIndex(step => router.isRouteActive(step.route)));

    function openStep(route: string): void {
        if (currentReview.assistantId === null) return;
        void router.goToRoute(route, {assistantId: currentReview.assistantId});
    }
</script>

<SidebarItems>
    {#if inDetailFlow}
        <ol class="wizard-steps" aria-label={__('assistants.admin.steps.progress')}>
            {#each STEPS as step, index (step.route)}
                {@const active = index === currentStep}
                {@const done = index < currentStep}
                <li class="wizard-step" class:current={active} class:done={done}>
                    <SidebarItem
                        label={__(step.labelKey)}
                        {active}
                        onclick={() => openStep(step.route)}
                    >
                        {#snippet media()}
                            <StepRing number={index + 1} done={done}/>
                        {/snippet}
                    </SidebarItem>
                </li>
            {/each}
        </ol>
    {:else}
        {#each sections as section (section.id)}
            <SidebarItem
                icon={section.icon}
                label={__(section.labelKey)}
                href={{name: section.route}}
                active={router.isRouteActive(`admin.${section.id}`)}
            />
        {/each}
    {/if}
</SidebarItems>

<style>
    .wizard-steps {
        --step-gap: var(--space-1);
        display: flex;
        flex-direction: column;
        gap: var(--step-gap);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .wizard-step {
        position: relative;
    }

    /* Connector to the next step: fills downwards in the accent colour once
       its step is done (the builder's wizard connector). */
    .wizard-step:has(+ .wizard-step)::after {
        --connector-w: 2.4px;
        --connector-h: var(--space-3);
        content: '';
        position: absolute;
        z-index: 1;
        left: calc(var(--nav-item-pad-x) + var(--nav-icon-size) / 2 - var(--connector-w) / 2);
        top: calc(100% + (var(--step-gap) - var(--connector-h)) / 2);
        width: var(--connector-w);
        height: var(--connector-h);
        border-radius: var(--corner-full);
        background:
            linear-gradient(var(--color-accent-fill), var(--color-accent-fill)) top / 100% 0% no-repeat,
            color-mix(in oklab, var(--color-text) 16%, transparent);
        transition: background-size 320ms cubic-bezier(0.3, 0, 0.2, 1) 140ms;
        pointer-events: none;
    }

    .wizard-step.done:has(+ .wizard-step)::after {
        background-size: 100% 100%, auto;
    }

    .wizard-step :global(.sidebar-item) {
        font-size: var(--font-size-nav);
    }

    .wizard-step.done :global(.sidebar-item:not(.active)) {
        color: var(--color-text);
    }

    @media (prefers-reduced-motion: reduce) {
        .wizard-step:has(+ .wizard-step)::after {
            transition: none;
        }
    }
</style>
