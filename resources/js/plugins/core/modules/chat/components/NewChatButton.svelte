<!--
  @component The chat module's primary sidebar action: the "New Chat" button,
  contributed to the app sidebar's pinned action area via the `sidebarSlots`
  hook (see `CorePlugin.hooks()`). Self-contained on purpose — it owns its
  store access and the mobile-nav collapse, so the sidebar shell only renders
  it, and any plugin can contribute an action button of its own the same way.
-->
<script lang="ts">
    import SidebarButton from '$lib/components/ui/sidebar/SidebarButton.svelte';
    import BubbleChatAddIcon from '$lib/components/ui/icons/iconset/BubbleChatAddIcon.svelte';
    import Kbd from '$lib/components/ui/kbd/Kbd.svelte';
    import {isApple} from '$lib/utils/platform.js';
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
        store.requestNewChat();
        void router.goToRoute('chat.index');
    }
</script>

<SidebarButton
    icon={BubbleChatAddIcon}
    label={__('chat.sidebar.newChat')}
    shortcut={isApple ? '⇧⌘O' : 'Ctrl Shift O'}
    onclick={newChat}
/>

<!-- The button shows its own hint, so the hotkey renders no keycap. -->
<Kbd key="o" ctrl shift onPress={newChat}>
    {#snippet children()}{/snippet}
</Kbd>
