<!--
  @component Assistants module sidebar with two drill levels. On dashboard
  routes it lists the dashboard sections (Store plus the "My assistants"
  collapsible with the personal sections); while a builder
  route is active the list is swapped for the builder's wizard — a
  header with the "new assistant" title and "step x of y", above a checklist of the numbered steps, joined by a
  connector line. Each step carries a ring that
  fills with its required fields and closes into a ticked disc once the step
  is done; steps that aren't reachable yet are locked. It is a drill-down, not an
  inline submenu, mirroring the mobile
  nav-stack pattern of DropdownMenuDetailView. The level is derived from the
  active route (the builder module's route group), so navigating in or out is
  what swaps it. The rows themselves are collected via the
  `assistantMenuEntries` hook (see `hooks/assistantMenuHooks.svelte.ts`) —
  the assistants plugin pushes the standard sections, other plugins may add
  their own. The module's "Erstellen" action and the builder's "Zurück"
  exit live in the app sidebar's action area (see `CreateAssistantButton.svelte`
  and `BuilderBackButton.svelte`).
-->
<script lang="ts">
    import SidebarItems from '$lib/components/ui/sidebar/SidebarItems.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import SidebarGroup from '$lib/components/ui/sidebar/SidebarGroup.svelte';
    import type {SidebarGroupItem} from '$lib/components/ui/sidebar/SidebarGroup.svelte';
    import UserAiIcon from '$lib/components/ui/icons/iconset/UserAiIcon.svelte';
    import {useAssistantMenuEntries} from '$plugins/assistants/hooks/assistantMenuHooks.svelte.js';
    import {useSidebarContext} from '$lib/app/ui/useSidebarHooks.svelte.js';
    import {assistantHandlesStore} from '$plugins/assistants/stores/AssistantHandlesStore.svelte.js';
    import {drillTransition} from '$lib/utils/transitions/drillTransition';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';
    import {builderProgress, isBuilderStepDone} from '$plugins/assistants/modules/builder/contexts/builderProgress.svelte.js';
    import {BUILDER_STEPS, type BuilderStep} from '$plugins/assistants/modules/builder/contexts/builderValidationRules.js';

    const router = useRouter();
    const sidebar = useSidebar();
    const {__} = useTranslator();

    const builderGroup = getModuleRouteGroupName('assistants', 'builder');

    /** The collected menu rows, grouped per drill level below. */
    const menu = useAssistantMenuEntries();
    const menuEntries = $derived(menu.entries);
    const sidebarContext = useSidebarContext();

    // Leaving the assistants module (dashboard *or* builder — within it this
    // component stays mounted) ends the visit: whatever the user changed there
    // (favourites, created/edited assistants) should be reflected in the chat
    // `@` menu, so its lazily-loaded list is marked stale. The next read — the
    // composer mounting on chat routes — refetches.
    $effect(() => () => assistantHandlesStore.invalidate());

    /**
     * The sidebar's main level: the dashboard sections that stay top-level
     * (Store, plus any ungrouped third-party rows). Grouped entries render in
     * the "My assistants" collapsible below them.
     */
    const dashboardItems = $derived(
        menuEntries.filter(entry => entry.level === 'dashboard' && !entry.group)
    );

    /** The entries collected under the "My assistants" collapsible group. */
    const myAssistantItems = $derived.by<SidebarGroupItem[]>(() =>
        menuEntries
            .filter(entry => entry.level === 'dashboard' && entry.group === 'my-assistants')
            .map(entry => ({
                id: entry.id,
                label: entry.label,
                icon: entry.icon,
                active: entry.active ?? (entry.route ? router.isRouteActive(entry.route) : false),
                onclick: () => openEntry(entry)
            }))
    );

    /** The drill-down level: the builder's sections in wizard order. */
    const wizardSteps = $derived(
        menuEntries
            .filter(entry => entry.level === 'builder')
            .map(entry => ({
                entry,
                active: entry.active ?? (entry.route ? router.isRouteActive(entry.route) : false)
            }))
    );

    /** Position of the open step among the wizard's steps (custom rows aside). */
    const stepRows = $derived(wizardSteps.filter(step => !step.entry.component));
    const stepCurrent = $derived(stepRows.findIndex(step => step.active) + 1);

    /** The builder step a section's route points at (`assistants.builder.<step>`), if any. */
    function stepOf(route?: string): BuilderStep | undefined {
        const step = route?.split('.').pop() as BuilderStep | undefined;
        return step && BUILDER_STEPS.includes(step) ? step : undefined;
    }

    /**
     * Which level the sidebar shows. While a builder route is active the main
     * nav is replaced by the builder sections (a drill-down); leaving the
     * builder returns to the main level. Route-derived, so the navigation
     * itself drives the level swap.
     */
    const inBuilder = $derived(router.isRouteActive(builderGroup));

    /** True while the nav is mid drill-down slide — suppresses the sliding
        highlights (see SidebarItems' `disabled`) so they don't chase the
        moving rows. */
    let navTransitioning = $state(false);
    let navTransitionTimer: ReturnType<typeof setTimeout> | undefined;
    function beginNavTransition() {
        navTransitioning = true;
        clearTimeout(navTransitionTimer);
        navTransitionTimer = setTimeout(() => (navTransitioning = false), 220);
    }

    /** Runs a collected row: its named route, or its custom `onSelect`. */
    function openEntry(entry: {route?: string; onSelect?: (ctx: typeof sidebarContext) => void}) {
        if (entry.route) {
            void router.goToRoute(entry.route);
        } else {
            entry.onSelect?.(sidebarContext);
        }
    }
</script>

<div class="assistants-sidebar">
    <SidebarItems disabled={navTransitioning}>
        <!-- Grid stack so the outgoing and incoming levels overlap in the same
             cell during the slide instead of stacking (which would collapse
             the layout upward when the outgoing level unmounts). -->
        <div class="nav-stack">
            {#if inBuilder}
                <!-- Builder level: the main nav is replaced by the wizard's
                     step list. Drills in from the right. -->
                <div
                    class="nav-level wizard"
                    class:collapsed={!sidebar.navOpen}
                    in:drillTransition
                    out:drillTransition
                    onintrostart={beginNavTransition}
                    onoutrostart={beginNavTransition}
                >
                    {#if stepCurrent}
                        <!-- Header on the steps' own inset: what is being
                             built, and where in the flow the user is. -->
                        <div class="wizard-header">
                            <p class="wizard-title">
                                {__('assistants.builder.sidebar.new_assistant')}
                            </p>
                            <p class="wizard-position">
                                <span class="wizard-position-current">
                                    {__('assistants.builder.steps.step_current', {current: stepCurrent})}
                                </span>
                                <span>{__('assistants.builder.steps.step_total', {total: stepRows.length})}</span>
                            </p>
                        </div>
                    {/if}
                    <ol class="wizard-steps" aria-label={__('assistants.builder.steps.progress')}>
                        {#each wizardSteps as {entry, active} (entry.id)}
                            {@const number = stepRows.findIndex(row => row.entry.id === entry.id) + 1}
                            {#if entry.component}
                                {@const Row = entry.component}
                                <li><Row /></li>
                            {:else}
                                {@const step = stepOf(entry.route)}
                                {@const done = !!step && isBuilderStepDone(step)}
                                <li class="wizard-step" class:current={active} class:done class:locked={entry.disabled}>
                                    <SidebarItem
                                        label={entry.label}
                                        {active}
                                        disabled={entry.disabled}
                                        onclick={() => openEntry(entry)}
                                    >
                                        <!-- Numbered ring that fills with the step's required
                                             fields; a done step closes into a ticked disc. -->
                                        {#snippet media()}
                                            {@const ratio = step ? builderProgress.ratio[step] ?? 0 : 0}
                                            <svg class="step-ring" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                <circle class="track" cx="10" cy="10" r="8"/>
                                                <circle class="arc" class:empty={ratio === 0} cx="10" cy="10" r="8"
                                                        pathLength="1" style:stroke-dashoffset={1 - ratio}/>
                                                <text class="number" x="10" y="10">{number}</text>
                                                <circle class="disc" cx="10" cy="10" r="9"/>
                                                <path class="tick" d="M6.25 10.5L8.75 13L13.75 7.5" pathLength="1"/>
                                            </svg>
                                        {/snippet}
                                    </SidebarItem>
                                </li>
                            {/if}
                        {/each}
                    </ol>
                </div>
            {:else}
                <!-- Main level. Drills back in from the left. -->
                <div
                    class="nav-level"
                    in:drillTransition={{direction: 'back'}}
                    out:drillTransition={{direction: 'back'}}
                    onintrostart={beginNavTransition}
                    onoutrostart={beginNavTransition}
                >
                    {#each dashboardItems as item (item.id)}
                        {#if item.component}
                            {@const Row = item.component}
                            <Row />
                        {:else}
                            <SidebarItem
                                icon={item.icon}
                                label={item.label}
                                active={item.active ?? (item.route ? router.isRouteActive(item.route) : false)}
                                onclick={() => openEntry(item)}
                            />
                        {/if}
                    {/each}
                    {#if myAssistantItems.length}
                        <SidebarGroup
                            label={__('assistants.sidebar.my_assistants')}
                            icon={UserAiIcon}
                            items={myAssistantItems}
                        />
                    {/if}
                </div>
            {/if}
        </div>
    </SidebarItems>
</div>

<style>
    .assistants-sidebar {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
        gap: var(--nav-group-gap);
    }

    .nav-stack {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        min-width: 0;
    }

    .nav-level {
        grid-area: 1 / 1;
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    /* Wizard ------------------------------------------------------------- */
    .wizard {
        --step-ring-size: 1.5rem;
        /* Tells the row's icon column how wide the ring really is (see
           SidebarItem's `.icon-wrap`). */
        --nav-media-size: var(--step-ring-size);
        --step-gap: var(--space-3);
    }

    /* On the steps' inset. Fades with the row labels in the rail instead of
       leaving the flow, so the steps don't jump. */
    .wizard-header {
        display: flex;
        flex-direction: column;
        gap: var(--space-0_5);
        padding: var(--space-1) var(--nav-item-pad-x) var(--space-2);
        overflow: hidden;
        white-space: nowrap;
        transition: opacity 160ms ease 100ms;
    }

    /* A notch above the step labels. */
    .wizard-title {
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }

    .wizard-position {
        display: flex;
        gap: var(--space-1);
        margin: 0;
        font-size: var(--font-size-xs);
        font-variant-numeric: tabular-nums;
        color: var(--color-text-muted);
    }

    .wizard-position-current {
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }

    .wizard.collapsed .wizard-header {
        opacity: 0;
        transition: opacity 100ms ease;
    }

    .wizard-steps {
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

    /* Connector to the next step: a line of fixed length on the ring column,
       centred between the two rings. It fills downwards in the accent colour
       once its step is done. */
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

    /* Step labels are the wizard's content, a notch above the nav rows. */
    .wizard-step :global(.sidebar-item) {
        font-size: var(--font-size-nav);
    }

    .wizard-step.done :global(.sidebar-item:not(.active)) {
        color: var(--color-text);
    }

    /* Locked steps recede, but stay readable as what is still ahead. */
    .wizard-step.locked :global(.sidebar-item) {
        color: color-mix(in oklab, var(--color-text-muted) 65%, transparent);
    }

    /* Outweighs the row's own glyph sizing (SidebarItem bumps svgs on mobile). */
    .wizard .step-ring {
        width: var(--step-ring-size);
        height: var(--step-ring-size);
        overflow: visible;
    }

    .step-ring .track,
    .step-ring .arc {
        stroke-width: 2;
    }

    /* In the row's own colour, so it follows hover, active and locked. */
    .step-ring .track {
        stroke: currentColor;
        opacity: 0.28;
    }

    /* The step's number, covered by the disc once the step is done. */
    .step-ring .number {
        fill: currentColor;
        font-size: 10.5px;
        font-weight: var(--font-weight-semibold);
        font-variant-numeric: tabular-nums;
        text-anchor: middle;
        dominant-baseline: central;
    }

    /* Starts at twelve o'clock and eases round as fields are filled. */
    .step-ring .arc {
        stroke: var(--color-accent-fill);
        stroke-linecap: round;
        stroke-dasharray: 1;
        rotate: -90deg;
        transform-origin: center;
        transition: stroke-dashoffset 420ms cubic-bezier(0.3, 0, 0.2, 1), opacity var(--duration-extra-fast);
    }

    .step-ring .arc.empty {
        opacity: 0;
    }

    /* Done: the disc grows out of the ring's centre with a small overshoot,
       then the tick draws itself in. */
    .step-ring .disc {
        fill: var(--color-accent-fill);
        transform-origin: center;
        scale: 0;
        transition: scale var(--duration-extra-fast) var(--easing-out);
    }

    .step-ring .tick {
        stroke: var(--color-on-accent-fill);
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 1;
        stroke-dashoffset: 1;
        transition: stroke-dashoffset var(--duration-extra-fast) var(--easing-out);
    }

    .wizard-step.done .disc {
        scale: 1;
        transition: scale 360ms cubic-bezier(0.3, 1.6, 0.5, 1);
    }

    .wizard-step.done .tick {
        stroke-dashoffset: 0;
        transition-delay: 140ms;
    }

    @media (prefers-reduced-motion: reduce) {
        .wizard-step:has(+ .wizard-step)::after,
        .step-ring .arc,
        .step-ring .disc,
        .step-ring .tick,
        .wizard-step.done .disc,
        .wizard-step.done .tick {
            transition: none;
        }
    }
</style>
