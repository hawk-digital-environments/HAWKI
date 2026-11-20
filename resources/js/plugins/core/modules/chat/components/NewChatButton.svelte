<!--
  @component The chat module's primary sidebar action: the "New Chat" button,
  contributed to the app sidebar's pinned action area via the `sidebarSlots`
  hook (see `CorePlugin.hooks()`). Self-contained on purpose — it owns its
  store access and the mobile-nav collapse, so the sidebar shell only renders
  it, and any plugin can contribute an action button of its own the same way.
-->
<script lang="ts">
    import SidebarButton from '$lib/components/ui/sidebar/SidebarButton.svelte';
    import Add01Icon from '$lib/components/ui/icons/iconset/Add01Icon.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';

    const store = useStore('chat');
    const router = useRouter();
    const sidebar = useSidebar();
    const {__} = useTranslator();

    function newChat() {
        if (sidebar.mobile) sidebar.navOpen = false;
        store.startNew();
        void router.goToRoute('chat.index');
    }
</script>

<SidebarButton
    icon={Add01Icon}
    label={__('chat.sidebar.newChat')}
    onclick={newChat}
/>
