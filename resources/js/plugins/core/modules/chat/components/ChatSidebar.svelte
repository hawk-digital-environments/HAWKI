<script lang="ts">
    import SidebarItems from '$lib/components/ui/sidebar/SidebarItems.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import SidebarButton from '$lib/components/ui/sidebar/SidebarButton.svelte';
    import Add01Icon from '$lib/components/ui/icons/iconset/Add01Icon.svelte';
    import {useSidebar} from '$lib/components/ui/sidebar/SidebarState.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const store = useStore('chat');
    // Rendered in the app sidebar, outside the RouterView subtree, so the
    // router context set there is not reachable — the app-level handle is
    // used instead.
    const app = useApp();
    const sidebar = useSidebar();
    const {__} = useTranslator();
    const expanded = $derived(sidebar.navOpen);

    function newChat() {
        store.startNew();
        void app.router.goTo(app.router.p('/chat'));
    }
</script>

<div class="chat-sidebar">
    <SidebarButton
        icon={Add01Icon}
        label={__('chat.sidebar.newChat')}
        onclick={newChat}
    />

    {#if expanded}
        <div class="history" aria-label={__('chat.sidebar.history')}>
            {#if store.listLoading && store.conversations.length === 0}
                <p class="hint">{__('chat.sidebar.loading')}</p>
            {:else if store.conversations.length === 0}
                <p class="hint">{__('chat.sidebar.empty')}</p>
            {:else}
                <SidebarItems>
                    {#each store.conversations as conversation (conversation.slug)}
                        {@const generating = store.isGenerating(conversation.slug)}
                        <!-- History rows are label-only: with every row carrying
                             the same message icon it added no information. The
                             leading slot is used solely to mark a conversation
                             that is still generating. -->
                        {#snippet generatingIndicator()}
                            <span class="generation-indicator" aria-hidden="true"></span>
                        {/snippet}
                        <SidebarItem
                            media={generating ? generatingIndicator : undefined}
                            label={conversation.name}
                            active={app.router.isActive(`/chat/${conversation.slug}`)}
                            aria-label={generating
                                ? `${conversation.name}, ${__('chat.sidebar.generating')}`
                                : conversation.name}
                            onclick={() => app.router.goTo(app.router.p(`/chat/${conversation.slug}`))}
                        />
                    {/each}
                </SidebarItems>
            {/if}
        </div>
    {/if}
</div>

<style>
    .chat-sidebar {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
        gap: var(--nav-group-gap);
    }

    .history {
        min-height: 0;
        overflow-y: auto;
    }

    .hint {
        margin: var(--space-3);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
    }

    .generation-indicator {
        width: 0.7rem;
        height: 0.7rem;
        border: 2px solid var(--color-border);
        border-top-color: var(--color-interactive);
        border-right-color: var(--color-interactive);
        border-radius: 50%;
        animation: generation-spin 700ms linear infinite;
    }

    @keyframes generation-spin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        .generation-indicator {
            border-color: var(--color-interactive);
            animation: none;
        }
    }
</style>
