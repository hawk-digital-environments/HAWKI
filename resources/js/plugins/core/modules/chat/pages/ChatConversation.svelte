<!--
@component Page component for the chat module's `/:slug` conversation route
(route name `chat.conversation`, see `ChatModule.ts`). Loads and renders one
existing conversation: the header with rename/export/delete, the scrollable,
bottom-pinned message log, and the docked composer. New chats start on the
sibling `ChatIndex.svelte` page and navigate here once the conversation
exists; a generation started there keeps streaming through the store.
-->
<script lang="ts">
    import type {RouteParams} from 'universal-router';
    import ChatComposer from '$plugins/core/snippets/ChatComposer.svelte';
    import ChatComposerDock from '$plugins/core/modules/chat/components/ChatComposerDock.svelte';
    import ChatHeader from '$plugins/core/modules/chat/components/ChatHeader.svelte';
    import ChatMessage from '$plugins/core/modules/chat/components/ChatMessage.svelte';
    import ChatWelcome from '$plugins/core/modules/chat/components/ChatWelcome.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ArrowReloadHorizontalIcon from '$lib/components/ui/icons/iconset/ArrowReloadHorizontalIcon.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {ChatTransport} from '$plugins/core/modules/chat/transport/ChatTransport.js';
    import {exportConversation} from '$plugins/core/modules/chat/utils/exportConversation.js';
    import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ChatMessage as ChatMessageType} from '$plugins/core/modules/chat/types.js';

    interface Props { params?: RouteParams; }
    const {params = {}}: Props = $props();
    const app = useApp();
    const store = useStore('chat');
    const router = useRouter();
    const toast = useToastContext();
    const {__} = useTranslator();
    const slug = $derived(typeof params.slug === 'string' ? params.slug : null);
    const defaultPrompt = app.stores.get('system-prompts').getPromptByType('default')?.prompt ?? '';
    const transport = new ChatTransport(app, store);

    let composer = $state<ComposerContext | null>(null);
    let messageToDelete = $state<ChatMessageType | null>(null);
    let scrollRegion = $state<HTMLDivElement | null>(null);
    let messagesElement = $state<HTMLDivElement | null>(null);
    let composerDockHeight = $state(0);
    let previousConversationSlug: string | null = null;
    let previousMessageCount = 0;
    let keepScrolledToBottom = false;
    let liveAnnouncement = $state('');
    let announcementConversationSlug: string | null = null;
    let wasGenerating = false;

    $effect(() => {
        const requestedSlug = slug;
        if (!requestedSlug) return;
        if (store.active?.slug !== requestedSlug) {
            store.load(requestedSlug).catch(error => toast.error(error instanceof Error ? error.message : String(error)));
        }
    });

    $effect(() => {
        const conversationSlug = store.active?.slug ?? null;
        const count = store.active?.messages.length ?? 0;
        const region = scrollRegion;
        const messages = messagesElement;

        if (!conversationSlug) {
            previousConversationSlug = null;
            previousMessageCount = 0;
            keepScrolledToBottom = false;
            return;
        }
        if (store.loading || !region || !messages) return;

        const conversationOpened = conversationSlug !== previousConversationSlug;
        const messageAdded = !conversationOpened && count > previousMessageCount;

        if (conversationOpened || messageAdded) {
            keepScrolledToBottom = true;
            requestAnimationFrame(() => {
                if (store.active?.slug === conversationSlug && scrollRegion === region) {
                    if (conversationOpened) {
                        region.scrollTop = region.scrollHeight;
                    } else {
                        region.scrollTo({top: region.scrollHeight, behavior: 'smooth'});
                    }
                }
            });
        }

        previousConversationSlug = conversationSlug;
        previousMessageCount = count;
    });

    $effect(() => {
        const region = scrollRegion;
        const messages = messagesElement;
        if (!region || !messages || typeof ResizeObserver === 'undefined') return;

        const observer = new ResizeObserver(() => {
            if (keepScrolledToBottom) region.scrollTop = region.scrollHeight;
        });
        // border-box, so the pin also fires when the reserved composer-dock
        // padding is measured/updated after the messages already rendered.
        observer.observe(messages, {box: 'border-box'});
        return () => observer.disconnect();
    });

    $effect(() => {
        const conversationSlug = store.active?.slug ?? null;
        const generating = conversationSlug ? store.isGenerating(conversationSlug) : false;

        if (conversationSlug !== announcementConversationSlug) {
            announcementConversationSlug = conversationSlug;
            wasGenerating = generating;
            liveAnnouncement = '';
            return;
        }

        if (wasGenerating && !generating) {
            liveAnnouncement = __('chat.page.responseReady');
        } else if (generating) {
            liveAnnouncement = '';
        }
        wasGenerating = generating;
    });

    function updateBottomPin() {
        if (!scrollRegion) return;
        const remaining = scrollRegion.scrollHeight - scrollRegion.clientHeight - scrollRegion.scrollTop;
        keepScrolledToBottom = remaining <= 2;
    }

    async function removeConversation() {
        if (!store.active) return;
        try {
            await store.remove(store.active.slug);
            void router.goTo(router.p('/chat'));
        } catch (error) {
            toast.error(error instanceof Error ? error.message : String(error));
        }
    }

    async function removeMessage() {
        if (!messageToDelete) return;
        try {
            await store.removeMessage(messageToDelete.message_id);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : String(error));
        } finally {
            messageToDelete = null;
        }
    }

    async function removeAttachment(message: ChatMessageType, fileId: string) {
        try {
            await store.removeAttachment(message.message_id, fileId);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : String(error));
        }
    }
</script>

<section class="chat-page" style:--composer-dock-height="{composerDockHeight}px">
    <div class="u-sr-only" role="status" aria-live="polite" aria-atomic="true">
        {liveAnnouncement}
    </div>
    {#if store.active}
        <ChatHeader
            conversation={store.active}
            generating={store.isGenerating(store.active.slug)}
            onRename={name => store.rename(store.active!.slug, name)}
            onDelete={removeConversation}
            onExport={format => store.active && exportConversation(store.active, format)}
            onSkipToComposer={() => composer?.focusInput()}
        />
    {:else}
        <!-- Keeps the header row of the page grid while the conversation is
             still loading or failed to load. -->
        <header class="placeholder-header" aria-hidden="true"></header>
    {/if}

    <div class="chat-body">
        <div class="scroll-region" bind:this={scrollRegion} onscroll={updateBottomPin}>
            {#if store.loading}
                <div class="state"><span class="spinner"></span><p>{__('chat.page.loading')}</p></div>
            {:else if store.error}
                <div class="state error">
                    <p>{store.error}</p>
                    {#if slug}
                        <Button variant="stroke" size="sm" iconLeft={ArrowReloadHorizontalIcon} onclick={() => store.load(slug)}>
                            {__('chat.page.retry')}
                        </Button>
                    {/if}
                </div>
            {:else if !store.active || store.active.messages.length === 0}
                <ChatWelcome />
            {:else}
                <div
                    class="messages"
                    bind:this={messagesElement}
                    role="log"
                    aria-live="polite"
                    aria-relevant="additions"
                    aria-label={__('chat.page.messageHistory')}
                >
                    {#each store.active.messages as message (message.message_id)}
                        <ChatMessage {message} {composer} onDelete={item => messageToDelete = item} onDeleteAttachment={removeAttachment} />
                    {/each}
                </div>
            {/if}
        </div>

        {#if !store.loading && !store.error}
            <ChatComposerDock {scrollRegion} bind:height={composerDockHeight}>
                {#key slug}
                    <ChatComposer
                        context="aiConv"
                        {transport}
                        backgroundActive={store.isGenerating(store.active?.slug)}
                        initialSystemPrompt={store.active?.system_prompt ?? defaultPrompt}
                        onSystemPromptChange={prompt => store.active && store.updateSystemPrompt(store.active.slug, prompt)}
                        onImproveMessage={(message, systemPrompt) => transport.improveMessage(message, systemPrompt)}
                        onReady={value => composer = value}
                    />
                {/key}
            </ChatComposerDock>
        {/if}
    </div>
</section>

<ConfirmDialog
    open={messageToDelete !== null}
    onOpenChange={open => !open && (messageToDelete = null)}
    title={__('chat.actions.deleteConfirmTitle')}
    description={__('chat.actions.deleteConfirmDescription')}
    onConfirm={removeMessage}
/>

<style>
    .chat-page {
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        height: 100%;
        min-height: 0;
        background: var(--color-surface-raised);
    }

    /* Shared canvas for the scroll region and the floating composer: the
       messages scroll behind the docked composer box. */
    .chat-body {
        position: relative;
        min-height: 0;
    }

    .placeholder-header {
        min-height: 3.75rem;
        border-bottom: var(--divider);
    }

    .scroll-region { height: 100%; overflow-y: auto; }

    .messages {
        display: flex;
        width: min(100%, 52rem);
        margin: 0 auto;
        padding: var(--space-8) var(--space-5) calc(var(--composer-dock-height, 0px) + var(--space-5));
        flex-direction: column;
        gap: var(--space-7);
    }

    .state {
        display: flex;
        height: 100%;
        min-height: 18rem;
        align-items: center;
        justify-content: center;
        padding: var(--space-6);
        padding-bottom: calc(var(--composer-dock-height, 0px) + var(--space-6));
        flex-direction: column;
        gap: var(--space-3);
        text-align: center;
    }

    .state p { max-width: 34rem; margin: 0; color: var(--color-text-muted); }
    .error p { color: var(--color-error); }

    .spinner {
        width: 1.5rem;
        height: 1.5rem;
        border: 2px solid var(--color-border);
        border-top-color: var(--color-interactive);
        border-radius: 50%;
        animation: spin 700ms linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 640px) {
        .messages {
            padding-inline: var(--space-3);
            padding-top: var(--space-5);
        }
    }

    @media print {
        :global(.app-sidebar) { display: none !important; }
        .chat-page > :global(header) { display: none !important; }
        .chat-page, .chat-body, .scroll-region { display: block; height: auto; overflow: visible; }
        .messages { padding-bottom: var(--space-5); }
    }
</style>
