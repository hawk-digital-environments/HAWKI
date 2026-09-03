<!--
  @component Assistants module sidebar with two drill levels. On dashboard
  routes it lists the dashboard sections (Store/Entwürfe/…); while a builder
  route is active the list is swapped for the builder's sections plus a
  "Zurück" row — a drill-down, not an inline submenu, mirroring the mobile
  nav-stack pattern of DropdownMenuDetailView. The level is derived from the
  active route (the builder module's route group), so navigating in or out is
  what swaps it. The rows themselves are collected via the
  `assistantMenuEntries` hook (see `hooks/assistantMenuHooks.svelte.ts`) —
  the assistants plugin pushes the standard sections, other plugins may add
  their own. The module's "Erstellen" action lives in the app sidebar's
  action area now (see `CreateAssistantButton.svelte`).
-->
<script lang="ts">
    import SidebarItems from '$lib/components/ui/sidebar/SidebarItems.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import {useAssistantMenuEntries} from '$plugins/assistants/hooks/assistantMenuHooks.svelte.js';
    import {useSidebarContext} from '$lib/app/ui/useSidebarHooks.svelte.js';
    import {assistantHandlesStore} from '$plugins/assistants/stores/AssistantHandlesStore.svelte.js';
    import {drillTransition} from '$lib/utils/transitions/drillTransition';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';

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

    /** The sidebar's main level: the dashboard sections. */
    const dashboardItems = $derived(menuEntries.filter(entry => entry.level === 'dashboard'));

    /** The drill-down level: the builder's sections, in builder tab order. */
    const builderSections = $derived(menuEntries.filter(entry => entry.level === 'builder'));

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

    /** Drill back out of the builder to the assistant dashboard. */
    function exitBuilder() {
        router.goToRoute('assistants.dashboard.store');
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
                    <SidebarItem
                        icon={ArrowLeft01Icon}
                        label={__('assistants.sidebar.back')}
                        onclick={exitBuilder}
                    />
                    {#each builderSections as section (section.id)}
                        {#if section.component}
                            {@const Row = section.component}
                            <Row />
                        {:else}
                            <SidebarItem
                                icon={section.icon}
                                label={section.label}
                                active={section.active ?? (section.route ? router.isRouteActive(section.route) : false)}
                                onclick={() => openEntry(section)}
                            />
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
