<!--
  @component One admin nav group (AI, people, system). In the open sidebar the
  row expands its sections inline; in the collapsed rail it opens a dropdown
  listing the same sections, so a section can be reached without expanding
  the sidebar.
-->
<script lang="ts">
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useRouter } from '$lib/components/ui/routing/index.js';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import { useSidebar } from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import type { IconComponent } from '$lib/components/ui/icons/index.js';
    import { sections } from '../sections.js';

    let { group, icon }: { group: (typeof sections)[number]['group']; icon: IconComponent } = $props();
    const app = useApp();
    const router = useRouter();
    const sidebar = useSidebar();
    const { __ } = useTranslator();
    const visible = $derived(sections.filter((section) => section.group === group && app.can(section.permission)));
    const activeSection = $derived(visible.find((section) => router.isRouteActive(`admin.${section.id}`))?.id);
    const collapsed = $derived(!sidebar.navOpen);
    let expanded = $state(false);
    let menuOpen = $state(false);

    $effect(() => {
        if (activeSection) expanded = true;
    });

    function openSection(id: string): void {
        menuOpen = false;
        void router.goToRoute(`admin.${id}`);
    }
</script>

{#if visible.length}
    {#if collapsed}
        <DropdownMenu
            bind:open={menuOpen}
            title={__('admin.groups.' + group)}
            side="right"
            align="start"
            sideOffset={12}
        >
            {#snippet trigger({ props })}
                <SidebarItem
                    label={__('admin.groups.' + group)}
                    {icon}
                    active={menuOpen || !!activeSection}
                    {...props}
                />
            {/snippet}
            {#each visible as section (section.id)}
                <DropdownMenuItem
                    aria-current={section.id === activeSection ? 'page' : undefined}
                    onclick={() => openSection(section.id)}
                >
                    {__('admin.sections.' + section.id)}
                </DropdownMenuItem>
            {/each}
        </DropdownMenu>
    {:else}
        <SidebarItem label={__('admin.groups.' + group)} {icon} bind:expanded>
            {#each visible as section (section.id)}
                <SidebarItem
                    indent
                    href={{ name: `admin.${section.id}` }}
                    active={router.isRouteActive(`admin.${section.id}`)}
                    label={__('admin.sections.' + section.id)}
                />
            {/each}
        </SidebarItem>
    {/if}
{/if}
