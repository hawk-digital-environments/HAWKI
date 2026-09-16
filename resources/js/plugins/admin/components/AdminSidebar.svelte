<script lang="ts">
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useRouter } from '$lib/components/ui/routing/index.js';
    import AdminSidebarSection from './AdminSidebarSection.svelte';
    import Home01Icon from '$lib/components/ui/icons/iconset/Home01Icon.svelte';
    import SidebarItems from '$lib/components/ui/sidebar/SidebarItems.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    const app = useApp();
    const router = useRouter();
    const { __ } = useTranslator();
</script>

<nav aria-label={__('admin.title')}>
    <SidebarItems>
        <SidebarItem
            icon={Home01Icon}
            href={{ name: 'admin.index' }}
            active={router.isRouteActive('admin.index')}
            label={__('admin.overview')}
        />
        {#each app.admin.sections as section (section.name)}
            <AdminSidebarSection {section} />
        {/each}
    </SidebarItems>
</nav>

<style>
    /* The sidebar shell already pads the column; extra padding here would
       leave no room for the icons in the collapsed rail. */
    nav {
        min-height: 0;
        flex: 1;
        overflow-y: auto;
    }
</style>
