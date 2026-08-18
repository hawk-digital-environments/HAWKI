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

    // Fade the list edge only where there is actually more content in that
    // direction, so a short (or fully scrolled) list shows no fade at all.
    let historyEl = $state<HTMLElement | null>(null);
    let fadeTop = $state(false);
    let fadeBottom = $state(false);

    function updateFades() {
        const el = historyEl;
        if (!el) return;
        const max = el.scrollHeight - el.clientHeight;
        fadeTop = el.scrollTop > 1;
        fadeBottom = el.scrollTop < max - 1;
    }

    $effect(() => {
        const el = historyEl;
        if (!el) return;
        // Re-measure when the list itself changes, not just on scroll.
        const observer = new ResizeObserver(updateFades);
        observer.observe(el);
        for (const child of el.children) observer.observe(child);
        updateFades();
        return () => observer.disconnect();
    });

    function newChat() {
        store.startNew();
        void app.router.goTo(app.router.p('/chat'));
    }
</script>

<div class="chat-sidebar">
    {#if expanded}
        <div
            class="history"
            class:fade-top={fadeTop}
            class:fade-bottom={fadeBottom}
            bind:this={historyEl}
            onscroll={updateFades}
            aria-label={__('chat.sidebar.history')}
        >
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

    <!-- Pinned to the bottom of the column, directly above the profile
         footer; the history scroller above it takes the free space. -->
    <div class="new-chat">
        <SidebarButton
            icon={Add01Icon}
            label={__('chat.sidebar.newChat')}
            onclick={newChat}
        />
    </div>
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
        flex: 1;
        overflow-y: auto;
        /* The list still scrolls, but without a visible bar — same treatment
           as the other scrollable panels (see DropdownMenuDetailView). */
        scrollbar-width: none;
        -ms-overflow-style: none;
        /* Both edges are collapsed to zero by default; the scroll state opens
           the one that has content beyond it. */
        --fade-top: 0px;
        --fade-bottom: 0px;
        --history-fade: linear-gradient(
            to bottom,
            transparent 0,
            black var(--fade-top),
            black calc(100% - var(--fade-bottom)),
            transparent 100%
        );
        mask-image: var(--history-fade);
        -webkit-mask-image: var(--history-fade);
    }

    .history.fade-top {
        --fade-top: var(--space-6);
    }

    .history.fade-bottom {
        --fade-bottom: var(--space-6);
    }

    .history::-webkit-scrollbar {
        display: none;
    }

    /* Keeps the button on the bottom edge even in the collapsed rail, where
       the history above it is not rendered at all. */
    .new-chat {
        margin-top: auto;
    }

    .hint {
        margin: var(--space-3);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
    }

    .generation-indicator {
        height: 1rem;
        aspect-ratio: 1;
        border: 2px solid var(--color-surface);
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
