<script lang="ts">
    import SidebarItems from '$lib/components/ui/sidebar/SidebarItems.svelte';
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import Add01Icon from '$lib/components/ui/icons/iconset/Add01Icon.svelte';
    import Message01Icon from '$lib/components/ui/icons/iconset/Message01Icon.svelte';
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
    const newChatLabel = $derived(__('chat.sidebar.newChat'));

    let newChatIcon = $state<HTMLElement | null>(null);

    function newChat() {
        store.startNew();
        void app.router.goTo(app.router.p('/chat'));
    }
</script>

<div class="chat-sidebar">
    <Tooltip
        tooltip={newChatLabel}
        side="right"
        sideOffset={24}
        delayDuration={300}
        customAnchor={newChatIcon}
        disabled={expanded}
    >
        {#snippet children({props})}
            <button
                type="button"
                {...props}
                class="new-chat"
                class:collapsed={!expanded}
                aria-label={newChatLabel}
                onclick={newChat}
            >
                <span class="new-chat-icon" bind:this={newChatIcon}>
                    <Add01Icon size={18} strokeWidth={2.25} aria-hidden="true" />
                </span>
                <span class="new-chat-label">{newChatLabel}</span>
            </button>
        {/snippet}
    </Tooltip>

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
                        {#snippet conversationIcon()}
                            <span class="conversation-icon">
                                <Message01Icon size={18} strokeWidth={2} aria-hidden="true" />
                                {#if generating}
                                    <span class="generation-indicator" aria-hidden="true"></span>
                                {/if}
                            </span>
                        {/snippet}
                        <SidebarItem
                            media={conversationIcon}
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
        gap: var(--space-3);
    }

    .new-chat {
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        width: 100%;
        min-height: var(--nav-row-h);
        padding: 0 var(--space-2_5) 0 var(--nav-item-pad-x);
        overflow: hidden;
        flex-shrink: 0;
        border: 0;
        border-radius: var(--corner-sm);
        background: var(--gradient-brand);
        color: var(--color-on-accent-fill);
        font: inherit;
        font-size: var(--font-size-nav);
        font-weight: var(--font-weight-semibold);
        text-align: left;
        white-space: nowrap;
        cursor: pointer;
        transition:
            background var(--duration-fast),
            box-shadow var(--duration-fast);
    }

    .new-chat:hover {
        background: var(--gradient-brand-hover);
        box-shadow: var(--elevation-1);
    }

    .new-chat:focus-visible {
        outline: 2px solid var(--color-focus-ring, var(--color-interactive));
        outline-offset: 2px;
    }

    .new-chat-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--nav-icon-size);
        height: var(--nav-icon-size);
        flex-shrink: 0;
    }

    .new-chat-label {
        overflow: hidden;
        text-overflow: ellipsis;
        transition: opacity 160ms ease 100ms;
    }

    .new-chat.collapsed .new-chat-label {
        opacity: 0;
        pointer-events: none;
        transition: opacity 100ms ease;
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

    .conversation-icon {
        position: relative;
        display: inline-flex;
    }

    .generation-indicator {
        position: absolute;
        right: -0.2rem;
        bottom: -0.2rem;
        width: 0.55rem;
        height: 0.55rem;
        border: 2px solid var(--color-surface);
        border-top-color: var(--color-interactive);
        border-right-color: var(--color-interactive);
        border-radius: 50%;
        background: var(--color-surface);
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
