<!--
  @component Page component for the chat module's `/:slug` conversation route
  (route name `chat.conversation`, see `ChatModule.ts`). Renders into the
  shared `ChatLayout`'s scroll region: one existing conversation's message
  log with its loading/error/empty states. The composer and the conversation
  header live in the layout; this page owns the scroll behaviour
  (pin-to-bottom, new-turn alignment) acting on the layout's scroll region
  through `ChatLayoutContext`.
-->
<script lang="ts">
    import type {RouteParams} from 'universal-router';
    import ChatMessage from '$plugins/core/modules/chat/components/ChatMessage.svelte';
    import ChatWelcome from '$plugins/core/modules/chat/components/ChatWelcome.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ArrowReloadHorizontalIcon from '$lib/components/ui/icons/iconset/ArrowReloadHorizontalIcon.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useChatComposerContext} from '$plugins/core/modules/chat/hooks/useChatComposerContext.svelte.js';
    import {useChatLayout} from '$plugins/core/modules/chat/components/ChatLayoutContext.svelte.js';
    import {groupMessagesIntoThreads, threadIndexOf} from '$plugins/core/modules/chat/utils/messageThreads.js';
    import type {ChatMessage as ChatMessageType} from '$plugins/core/modules/chat/types.js';

    interface Props { params?: RouteParams; }
    const {params = {}}: Props = $props();
    const store = useStore('chat');
    const toast = useToastContext();
    const {__} = useTranslator();
    // Reads the layout's composer session (see the hook's docs).
    const chat = useChatComposerContext();
    const layout = useChatLayout();
    const slug = $derived(typeof params.slug === 'string' ? params.slug : null);

    let messageToDelete = $state<ChatMessageType | null>(null);
    let messagesElement = $state<HTMLDivElement | null>(null);
    let newTurnActive = $state(false);
    let previousConversationSlug: string | null = null;
    let previousMessageCount = 0;
    // True from opening a conversation until the user scrolls up or sends a
    // message: the log keeps following its bottom edge while the messages
    // finish rendering (markdown, KaTeX, reasoning blocks) and grow in height.
    let pinToBottom = false;
    let liveAnnouncement = $state('');
    let announcementConversationSlug: string | null = null;
    let wasGenerating = false;

    // Trunk messages with their thread replies nested under them (legacy
    // `W.DDD` message ids): the log renders one turn per trunk message and
    // each `ChatMessage` renders its own thread.
    const threadGroups = $derived(store.active ? groupMessagesIntoThreads(store.active.messages) : []);

    // The composer lives in the layout and outlives this page, so re-land
    // the cursor whenever a conversation is opened.
    $effect(() => {
        if (!slug) return;
        chat.autoFocusInput();
    });

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
        const region = layout.scrollRegion;
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
            pinToBottom = true;
            requestAnimationFrame(() => {
                if (store.active?.slug === conversationSlug && layout.scrollRegion === region) {
                    region.scrollTop = region.scrollHeight;
                }
            });
        } else if (messageAdded && lastMessage?.message_role === 'user' && threadIndexOf(lastMessage) === 0) {
            // Thread replies are excluded: they render inside their trunk
            // message's thread, which is already in view — scrolling the last
            // trunk turn to the top would jump away from it.
            pinToBottom = false;
            // A freshly sent message starts a new turn: `new-turn` reserves a
            // screen of space below it, and this single scroll aligns it with
            // the top of the region. The streaming response then renders into
            // the reserved space — there is no follow-up or sticky scrolling.
            newTurnActive = true;
            requestAnimationFrame(() => {
                if (store.active?.slug !== conversationSlug || layout.scrollRegion !== region) return;
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

    // Messages keep growing after the open-scroll above (async markdown and
    // formula rendering, fonts, collapsed reasoning). Follow the bottom edge
    // for as long as the view is pinned there.
    $effect(() => {
        const region = layout.scrollRegion;
        const messages = messagesElement;
        if (!region || !messages) return;

        const observer = new ResizeObserver(() => {
            if (pinToBottom) region.scrollTop = region.scrollHeight;
        });
        observer.observe(messages);
        return () => observer.disconnect();
    });

    // Any scroll that leaves the bottom edge is the user's: release the pin.
    $effect(() => {
        const region = layout.scrollRegion;
        if (!region) return;
        const releasePin = () => {
            if (!pinToBottom) return;
            const remaining = region.scrollHeight - region.clientHeight - region.scrollTop;
            if (remaining > 2) pinToBottom = false;
        };
        region.addEventListener('scroll', releasePin, {passive: true});
        return () => region.removeEventListener('scroll', releasePin);
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

<div class="u-sr-only" role="status" aria-live="polite" aria-atomic="true">
    {liveAnnouncement}
</div>

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
        {#each threadGroups as group (group.message.clientKey ?? group.message.message_id)}
            <ChatMessage message={group.message} replies={group.replies} composer={chat.composer} onDelete={item => messageToDelete = item} onDeleteAttachment={removeAttachment} />
        {/each}
    </div>
{/if}

<ConfirmDialog
    open={messageToDelete !== null}
    onOpenChange={open => !open && (messageToDelete = null)}
    title={__('chat.actions.deleteConfirmTitle')}
    description={__('chat.actions.deleteConfirmDescription')}
    onConfirm={removeMessage}
/>

<style>
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
        .messages { padding-bottom: var(--space-5); }
    }
</style>
