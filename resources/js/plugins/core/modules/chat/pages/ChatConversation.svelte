<!--
@component Page component for the chat module's `/:slug` conversation route
(route name `chat.conversation`, see `ChatModule.ts`). Loads and renders one
existing conversation: the header with rename/export/delete, the scrollable
message log, and the docked composer. New chats start on the
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
    const systemPromptStore = useStore('system-prompts');
    const router = useRouter();
    const toast = useToastContext();
    const {__} = useTranslator();
    const exportLabels = $derived({
        systemPrompt: __('chat.export.systemPrompt'),
        conversation: __('chat.export.conversation'),
        attachments: __('chat.export.attachments')
    });
    const slug = $derived(typeof params.slug === 'string' ? params.slug : null);
    const defaultPrompt = systemPromptStore.getPromptByType('default').prompt;
    const transport = new ChatTransport(app, store);

    let composer = $state<ComposerContext | null>(null);
    let messageToDelete = $state<ChatMessageType | null>(null);
    let scrollRegion = $state<HTMLDivElement | null>(null);
    let messagesElement = $state<HTMLDivElement | null>(null);
    let composerDockHeight = $state(0);
    let scrollRegionHeight = $state(0);
    let newTurnActive = $state(false);
    let previousConversationSlug: string | null = null;
    let previousMessageCount = 0;
    let liveAnnouncement = $state('');
    let announcementConversationSlug: string | null = null;
    let wasGenerating = false;

    // No messages yet: welcome text and composer are centred as one block
    // instead of the composer docking to the bottom of the scroll region.
    const isEmpty = $derived(!store.loading && !store.error && (!store.active || store.active.messages.length === 0));

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
            newTurnActive = false;
            return;
        }
        if (store.loading || !region || !messages) return;

        const conversationOpened = conversationSlug !== previousConversationSlug;
        const messageAdded = !conversationOpened && count > previousMessageCount;
        const lastMessage = store.active?.messages[count - 1] ?? null;

        if (conversationOpened) {
            newTurnActive = false;
            requestAnimationFrame(() => {
                if (store.active?.slug === conversationSlug && scrollRegion === region) {
                    region.scrollTop = region.scrollHeight;
                }
            });
        } else if (messageAdded && lastMessage?.message_role === 'user') {
            // A freshly sent message starts a new turn: `new-turn` reserves a
            // screen of space below it, and this single scroll aligns it with
            // the top of the region. The streaming response then renders into
            // the reserved space — there is no follow-up or sticky scrolling.
            newTurnActive = true;
            requestAnimationFrame(() => {
                if (store.active?.slug !== conversationSlug || scrollRegion !== region) return;
                const turn = messages.lastElementChild;
                if (!(turn instanceof HTMLElement)) return;
                // Leave room for the header fade that overhangs the scroll
                // region so the sent message is not covered by it.
                const offset = parseFloat(getComputedStyle(messages).getPropertyValue('--new-turn-scroll-offset')) || 0;
                const top = turn.getBoundingClientRect().top - region.getBoundingClientRect().top + region.scrollTop - offset;
                region.scrollTo({top, behavior: 'smooth'});
            });
        }

        previousConversationSlug = conversationSlug;
        previousMessageCount = count;
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

    async function removeConversation() {
        if (!store.active) return;
        try {
            await store.remove(store.active.slug);
            void router.goToRoute('chat.index');
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

<section
    class="chat-page"
    style:--composer-dock-height="{composerDockHeight}px"
    style:--scroll-region-height="{scrollRegionHeight}px"
>
    <div class="u-sr-only" role="status" aria-live="polite" aria-atomic="true">
        {liveAnnouncement}
    </div>
    {#if store.active}
        <ChatHeader
            conversation={store.active}
            generating={store.isGenerating(store.active.slug)}
            onRename={name => store.rename(store.active!.slug, name)}
            onDelete={removeConversation}
            onExport={format => {
                if (store.active) return exportConversation(store.active, format, exportLabels);
            }}
            onSkipToComposer={() => composer?.focusInput()}
        />
    {:else}
        <!-- Keeps the header row of the page grid while the conversation is
             still loading or failed to load. -->
        <header class="placeholder-header" aria-hidden="true"></header>
    {/if}

    <div class="chat-body" class:empty={isEmpty}>
        <div class="scroll-region" bind:this={scrollRegion} bind:clientHeight={scrollRegionHeight}>
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
                    class:new-turn={newTurnActive}
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
                        forcedActive={store.isGenerating(store.active?.slug)}
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

    /* Empty chat: the scroll region shrinks to its content so the welcome
       block and the composer sit together in the middle of the panel.
       The block is nudged above the true centre — the disclaimer adds
       visual weight at the bottom, so geometric centring reads as low. */
    .chat-body.empty {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding-bottom: var(--space-10);
        overflow-y: auto;
    }

    .empty .scroll-region { height: auto; flex: 0 0 auto; overflow: visible; }

    .empty :global(.welcome) {
        height: auto;
        min-height: 0;
        padding-bottom: var(--space-6);
    }

    .empty :global(.composer-dock) { position: static; padding-bottom: 0; }
    .empty :global(.composer-dock::before) { display: none; }

    .scroll-region { height: 100%; overflow-y: auto; }

    .messages {
        /* Pixel value, read by the send-scroll: the header fade overhangs the
           scroll region by 3rem, so the sent message stops that far below the
           top edge instead of flush against it. */
        --new-turn-scroll-offset: 42px;
        display: flex;
        width: min(100%, 52rem);
        margin: 0 auto;
        padding: var(--space-8) var(--space-5) calc(var(--composer-dock-height, 0px) + var(--space-5));
        flex-direction: column;
        gap: var(--space-7);
    }

    /* While a turn started in this session is the tail of the log, the last
       message reserves a screen of height. That gives the one-time scroll on
       send enough room to align the sent message with the top of the region,
       and the response streams into the reserved space without further
       scrolling. */
    .messages.new-turn > :global(:last-child) {
        min-height: calc(var(--scroll-region-height, 100dvh) - var(--composer-dock-height, 0px) - var(--space-8) - var(--new-turn-scroll-offset));
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

    @media (--bp-sm-and-smaller) {
        .messages {
            padding-inline: var(--space-3);
            padding-top: var(--space-5);
        }
    }

    @media print {
        .chat-page, .chat-body, .scroll-region { display: block; height: auto; overflow: visible; }
        .messages { padding-bottom: var(--space-5); }
    }
</style>
