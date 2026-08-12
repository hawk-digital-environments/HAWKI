<!--
  @component App-level layout assembly: the grid shell with the navigation
  sidebar in the `nav` column and the page content in the `main` column. The
  layout mechanics (open/closed state, rail collapse, off-canvas behaviour)
  live in the components under components/ui/sidebar.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import AppSidebar from '$lib/app/components/sidebar/AppSidebar.svelte';
    import SidebarContent from '$lib/components/ui/sidebar/SidebarContent.svelte';
    import SidebarContext from '$lib/components/ui/sidebar/SidebarContext.svelte';

    interface Props {
        /** Page content, rendered in the layout's main column. */
        children: Snippet;
    }

    const {children}: Props = $props();
</script>

<SidebarContext>
    <AppSidebar />
    <SidebarContent>
        {@render children()}
    </SidebarContent>
</SidebarContext>
