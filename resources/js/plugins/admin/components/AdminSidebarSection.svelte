<!--
  @component One Admin Panel Section. In the open sidebar the row expands its
  Workspaces inline; in the collapsed rail it opens a dropdown listing the
  same Workspaces, so every visible Workspace remains reachable.
-->
<script lang="ts">
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useRouter } from '$lib/components/ui/routing/index.js';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import { useSidebar } from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import type { RegisteredAdminSection } from '../api.js';

    let { section }: { section: RegisteredAdminSection } = $props();
    const app = useApp();
    const router = useRouter();
    const sidebar = useSidebar();
    const { __ } = useTranslator();
    const visible = $derived(section.workspaces.filter((workspace) => app.can(workspace.permission)));
    const activeWorkspace = $derived(visible.find((workspace) => router.isRouteActive(workspace.routeName))?.name);
    const collapsed = $derived(!sidebar.navOpen);
    let expanded = $state(false);
    let menuOpen = $state(false);

    $effect(() => {
        if (activeWorkspace) expanded = true;
    });

    function openWorkspace(routeName: string): void {
        menuOpen = false;
        void router.goToRoute(routeName);
    }
</script>

{#if visible.length}
    {#if collapsed}
        <DropdownMenu
            bind:open={menuOpen}
            title={__(section.title)}
            side="right"
            align="start"
            sideOffset={12}
        >
            {#snippet trigger({ props })}
                <SidebarItem
                    label={__(section.title)}
                    icon={section.icon}
                    active={menuOpen || !!activeWorkspace}
                    {...props}
                />
            {/snippet}
            {#each visible as workspace (workspace.name)}
                <DropdownMenuItem
                    aria-current={workspace.name === activeWorkspace ? 'page' : undefined}
                    onclick={() => openWorkspace(workspace.routeName)}
                >
                    {__(workspace.title)}
                </DropdownMenuItem>
            {/each}
        </DropdownMenu>
    {:else}
        <SidebarItem label={__(section.title)} icon={section.icon} bind:expanded>
            {#each visible as workspace (workspace.name)}
                <SidebarItem
                    indent
                    href={{ name: workspace.routeName }}
                    active={router.isRouteActive(workspace.routeName)}
                    label={__(workspace.title)}
                />
            {/each}
        </SidebarItem>
    {/if}
{/if}
