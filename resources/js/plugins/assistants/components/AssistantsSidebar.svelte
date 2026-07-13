<!--
  @component Assistants module sidebar with two drill levels. On dashboard
  routes it lists the dashboard sections (Store/Entwürfe/…); while a builder
  route is active the list is swapped for the builder's sections plus a
  "Zurück" row — a drill-down, not an inline submenu, mirroring the mobile
  nav-stack pattern of DropdownMenuDetailView. The level is derived from the
  active route (the builder module's route group), so navigating in or out is
  what swaps it. The "Erstellen" action sits pinned at the bottom like the
  chats' "Neuer Chat" button and drills into a fresh builder session.
-->
<script lang="ts">
    import SidebarItems from '$lib/components/ui/sidebar/SidebarItems.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import SidebarButton from '$lib/components/ui/sidebar/SidebarButton.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import Store01Icon from '$lib/components/ui/icons/iconset/Store01Icon.svelte';
    import FileEditIcon from '$lib/components/ui/icons/iconset/FileEditIcon.svelte';
    import StarIcon from '$lib/components/ui/icons/iconset/StarIcon.svelte';
    import Share02Icon from '$lib/components/ui/icons/iconset/Share02Icon.svelte';
    import AddCircleIcon from '$lib/components/ui/icons/iconset/AddCircleIcon.svelte';
    import ArrowLeft01Icon from '$lib/components/ui/icons/iconset/ArrowLeft01Icon.svelte';
    import Settings01Icon from '$lib/components/ui/icons/iconset/Settings01Icon.svelte';
    import BubbleChatIcon from '$lib/components/ui/icons/iconset/BubbleChatIcon.svelte';
    import Database01Icon from '$lib/components/ui/icons/iconset/Database01Icon.svelte';
    import ComputerIcon from '$lib/components/ui/icons/iconset/ComputerIcon.svelte';
    import TestTube01Icon from '$lib/components/ui/icons/iconset/TestTube01Icon.svelte';
    import SentIcon from '$lib/components/ui/icons/iconset/SentIcon.svelte';
    import {drillTransition} from '$lib/utils/transitions/drillTransition';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {getModuleRouteGroupName} from '$lib/kernel/routing/routeInflection.js';
    import {requestBuilderIntent} from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';

    const router = useRouter();
    const sidebar = useSidebar();
    const {__} = useTranslator();

    const builderGroup = getModuleRouteGroupName('assistants', 'builder');

    /** The sidebar's main level: the dashboard sections. */
    const dashboardItems = $derived([
        {
            label: __('assistants.sidebar.store'),
            icon: Store01Icon,
            route: 'assistants.dashboard.store',
            active: router.isRouteActive('assistants.dashboard.store')
                || router.isRouteActive('assistants.dashboard.index')
        },
        {
            label: __('assistants.sidebar.drafts'),
            icon: FileEditIcon,
            route: 'assistants.dashboard.drafts',
            active: router.isRouteActive('assistants.dashboard.drafts')
        },
        {
            label: __('assistants.sidebar.favourites'),
            icon: StarIcon,
            route: 'assistants.dashboard.favourites',
            active: router.isRouteActive('assistants.dashboard.favourites')
        },
        {
            label: __('assistants.sidebar.shared'),
            icon: Share02Icon,
            route: 'assistants.dashboard.shared',
            active: router.isRouteActive('assistants.dashboard.shared')
        }
    ]);

    /** The drill-down level: the builder's sections, in builder tab order. */
    const builderSections = $derived([
        {
            label: __('assistants.builder.sidebar.general'),
            icon: Settings01Icon,
            route: 'assistants.builder.general'
        },
        {
            label: __('assistants.builder.sidebar.behaviour'),
            icon: BubbleChatIcon,
            route: 'assistants.builder.behaviour'
        },
        {
            label: __('assistants.builder.sidebar.knowledge'),
            icon: Database01Icon,
            route: 'assistants.builder.knowledge'
        },
        {
            label: __('assistants.builder.sidebar.model'),
            icon: ComputerIcon,
            route: 'assistants.builder.model'
        },
        {
            label: __('assistants.builder.sidebar.test'),
            icon: TestTube01Icon,
            route: 'assistants.builder.test'
        },
        {
            label: __('assistants.builder.sidebar.publish'),
            icon: SentIcon,
            route: 'assistants.builder.publish'
        }
    ]);

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

    /** Drill back out of the builder to the assistant dashboard. */
    function exitBuilder() {
        router.goToRoute('assistants.dashboard.store');
    }

    /**
     * Drill into a fresh builder session: stash a create intent (the builder
     * layout picks it up and mints a new assistant — an explicit create, so
     * any restored session draft is discarded) and navigate to the builder's
     * first section, which flips the sidebar to the builder level.
     */
    function startCreate() {
        if (sidebar.mobile) sidebar.navOpen = false;
        requestBuilderIntent({type: 'create'});
        router.goToRoute('assistants.builder.general');
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
                    {#each builderSections as section (section.route)}
                        <SidebarItem
                            icon={section.icon}
                            label={section.label}
                            active={router.isRouteActive(section.route)}
                            onclick={() => router.goToRoute(section.route)}
                        />
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
                    {#each dashboardItems as item (item.route)}
                        <SidebarItem
                            icon={item.icon}
                            label={item.label}
                            active={item.active}
                            onclick={() => router.goToRoute(item.route)}
                        />
                    {/each}
                </div>
            {/if}
        </div>
    </SidebarItems>

    <!-- Pinned to the bottom of the column, directly above the profile footer -->
    {#if !inBuilder}
        <div class="create-assistant">
            <SidebarButton
                icon={AddCircleIcon}
                label={__('assistants.sidebar.create')}
                onclick={startCreate}
            />
        </div>
    {/if}
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

    .create-assistant {
        margin-top: auto;
    }
</style>
