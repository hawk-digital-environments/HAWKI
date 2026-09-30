<!--
  @component Assistants module sidebar with two drill levels. On dashboard
  routes it lists the dashboard sections (Store plus the "My assistants"
  collapsible with the personal sections); while a builder
  route is active the list is swapped for the builder's sections — a drill-down, not an inline submenu, mirroring the mobile
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
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';
    import BuilderStepTick from '$plugins/assistants/modules/builder/components/BuilderStepTick.svelte';
    import {isBuilderStepDone} from '$plugins/assistants/modules/builder/contexts/builderProgress.svelte.js';
    import {BUILDER_STEPS, type BuilderStep} from '$plugins/assistants/modules/builder/contexts/builderValidationRules.js';

    const router = useRouter();
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

    /** The drill-down level: the builder's sections, in builder tab order. */
    const builderSections = $derived(menuEntries.filter(entry => entry.level === 'builder'));

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
                <!-- Builder level: the main nav is replaced by the section
                     list. Drills in from the right. -->
                <div
                    class="nav-level"
                    in:drillTransition
                    out:drillTransition
                    onintrostart={beginNavTransition}
                    onoutrostart={beginNavTransition}
                >
                    {#each builderSections as section (section.id)}
                        {@const step = stepOf(section.route)}
                        {@const active = section.active ?? (section.route ? router.isRouteActive(section.route) : false)}
                        {#if section.component}
                            {@const Row = section.component}
                            <Row />
                        {:else}
                            <!-- A done builder step swaps its icon for a tick;
                                 one row either way, so only the glyph changes. -->
                            {@const done = !!step && isBuilderStepDone(step)}
                            {@const Icon = section.icon}
                            <SidebarItem
                                label={section.label}
                                {active}
                                disabled={section.disabled}
                                onclick={() => openEntry(section)}
                            >
                                {#snippet media()}
                                    {#if done}
                                        <BuilderStepTick/>
                                    {:else if Icon}
                                        <Icon size={18} strokeWidth={2} aria-hidden="true"/>
                                    {/if}
                                {/snippet}
                            </SidebarItem>
                        {/if}
                    {/each}
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
</style>
