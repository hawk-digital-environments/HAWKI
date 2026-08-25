<script lang="ts">
    import type {RouteParams} from 'universal-router';
    import ChatComposer from '$plugins/core/snippets/ChatComposer.svelte';
    import ChatHeader from '$plugins/core/modules/chat/components/ChatHeader.svelte';
    import ChatMessage from '$plugins/core/modules/chat/components/ChatMessage.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import ArrowReloadHorizontalIcon from '$lib/components/ui/icons/iconset/ArrowReloadHorizontalIcon.svelte';
    import AiChat01Icon from '$lib/components/ui/icons/iconset/AiChat01Icon.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {ChatTransport} from '$plugins/core/modules/chat/transport/ChatTransport.js';
    import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ChatMessage as ChatMessageType} from '$plugins/core/modules/chat/types.js';
    import type {OldUiExportType} from '$lib/legacy/OldUiBridge.svelte.js';

    interface Props { params?: RouteParams; }
    const {params = {}}: Props = $props();
    const app = useApp();
    const store = useStore('chat');
    const router = useRouter();
    const toast = useToastContext();
    const {__} = useTranslator();
    const slug = $derived(typeof params.slug === 'string' ? params.slug : null);
    const defaultPrompt = app.stores.get('system-prompts').getPromptByType('default')?.prompt ?? '';
    const transport = new ChatTransport(app, store, {
        // Do not pull the user back if they switched chats while the title was generated.
        onConversationCreated: createdSlug => {
            if (router.isActive('/chat')) void router.goTo(router.p(`/chat/${createdSlug}`));
        }
    });

    let composer = $state<ComposerContext | null>(null);
    let messageToDelete = $state<ChatMessageType | null>(null);
    let scrollRegion = $state<HTMLDivElement | null>(null);
    let messagesElement = $state<HTMLDivElement | null>(null);
    let composerDock = $state<HTMLDivElement | null>(null);
    let composerDockHeight = $state(0);
    let scrollbarGutter = $state(0);
    let previousConversationSlug: string | null = null;
    let previousMessageCount = 0;
    let keepScrolledToBottom = false;
    let liveAnnouncement = $state('');
    let announcementConversationSlug: string | null = null;
    let wasGenerating = false;

    // No messages yet: welcome text and composer are centred as one block
    // instead of the composer docking to the bottom of the scroll region.
    const isEmpty = $derived(!store.loading && !store.error && (!store.active || store.active.messages.length === 0));

    $effect(() => {
        const requestedSlug = slug;
        if (!requestedSlug) {
            store.startNew();
            return;
        }
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

    // The composer floats above the scroll region, so the messages reserve its
    // height as bottom padding — track it since the composer grows with input.
    $effect(() => {
        const dock = composerDock;
        if (!dock || typeof ResizeObserver === 'undefined') return;

        const observer = new ResizeObserver(() => {
            composerDockHeight = dock.offsetHeight;
        });
        observer.observe(dock);
        return () => {
            observer.disconnect();
            composerDockHeight = 0;
        };
    });

    // The messages centre themselves inside the scroll region minus its
    // scrollbar, while the dock spans the full panel — mirror the scrollbar
    // width as dock padding so the composer stays aligned with the text.
    $effect(() => {
        const region = scrollRegion;
        if (!region || typeof ResizeObserver === 'undefined') return;

        // The content box shrinks when the scrollbar (dis)appears, so this
        // also fires without the region's outer size changing.
        const observer = new ResizeObserver(() => {
            scrollbarGutter = region.offsetWidth - region.clientWidth;
        });
        observer.observe(region);
        return () => {
            observer.disconnect();
            scrollbarGutter = 0;
        };
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

    function exportConversation(format: OldUiExportType) {
        const conversation = store.active;
        if (!conversation) return;
        if (format === 'print' || format === 'pdf') {
            window.print();
            return;
        }

        const rows = conversation.messages.map(message => ({
            role: message.message_role,
            author: message.author.name,
            model: message.model,
            message: message.content.text,
            created_at: message.created_at
        }));
        if (format === 'json') {
            download(`${conversation.name}.json`, JSON.stringify({
                name: conversation.name,
                system_prompt: conversation.system_prompt,
                messages: rows
            }, null, 2), 'application/json');
        } else if (format === 'csv') {
            const quote = (value: unknown) => `"${String(value ?? '').replaceAll('"', '""')}"`;
            download(`${conversation.name}.csv`, [
                ['role', 'author', 'model', 'message', 'created_at'].map(quote).join(','),
                ...rows.map(row => Object.values(row).map(quote).join(','))
            ].join('\n'), 'text/csv');
        } else {
            const body = rows.map(row => `<h2>${escapeHtml(row.author)}</h2><p>${escapeHtml(row.message).replaceAll('\n', '<br>')}</p>`).join('');
            download(`${conversation.name}.doc`, `<html><body><h1>${escapeHtml(conversation.name)}</h1>${body}</body></html>`, 'application/msword');
        }
    }

    function download(filename: string, content: string, type: string) {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([content], {type}));
        link.download = filename.replace(/[\\/:*?"<>|]/g, '-');
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function escapeHtml(value: string): string {
        const element = document.createElement('div');
        element.textContent = value;
        return element.innerHTML;
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
            onExport={exportConversation}
            onSkipToComposer={() => composer?.focusInput()}
        />
    {/if}

    <div class="chat-body" class:empty={isEmpty}>
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
                <div class="welcome">
                    <span class="welcome-icon" aria-hidden="true"><AiChat01Icon size={28} /></span>
                    <h1>{__('chat.page.welcomeTitle')}</h1>
                    <p>{__('chat.page.welcomeDescription')}</p>
                </div>
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
            <div class="composer-dock" bind:this={composerDock} style:--scrollbar-gutter="{scrollbarGutter}px">
                <div class="composer-row">
                    {#key slug ?? 'new'}
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
                </div>
                <p class="disclaimer">{__('chat.page.disclaimer')}</p>
            </div>
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
        grid-row: 2;
        min-height: 0;
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

    .empty .welcome {
        height: auto;
        min-height: 0;
        padding-bottom: var(--space-6);
    }

    .empty .composer-dock { position: static; padding-bottom: 0; }
    .empty .composer-dock::before { display: none; }

    /* No messages to line up with, so drop the avatar column — otherwise the
       composer sits offset to the right of the centred welcome block. */
    .empty .composer-row { grid-template-columns: minmax(0, 1fr); }
    .empty .composer-row :global(.chat-composer-wrapper) { grid-column: 1; }

    .scroll-region { height: 100%; overflow-y: auto; }

    .messages {
        display: flex;
        width: min(100%, 52rem);
        margin: 0 auto;
        padding: var(--space-8) var(--space-5) calc(var(--composer-dock-height, 0px) + var(--space-5));
        flex-direction: column;
        gap: var(--space-7);
    }

    .welcome,
    .state {
        display: flex;
        height: 100%;
        min-height: 18rem;
        align-items: center;
        justify-content: center;
        padding: var(--space-6);
        padding-bottom: calc(var(--composer-dock-height, 0px) + var(--space-6));
        flex-direction: column;
        text-align: center;
    }

    .welcome-icon {
        display: grid;
        width: 3.25rem;
        height: 3.25rem;
        margin-bottom: var(--space-4);
        place-items: center;
        border-radius: var(--corner-lg);
        background: var(--color-active-surface);
        color: var(--color-active-text);
    }

    h1 { margin: 0 0 var(--space-2); font-size: var(--font-size-xl); font-weight: var(--font-weight-medium); }
    .welcome p, .state p { max-width: 34rem; margin: 0; color: var(--color-text-muted); }
    .state { gap: var(--space-3); }
    .error p { color: var(--color-error); }

    .spinner {
        width: 1.5rem;
        height: 1.5rem;
        border: 2px solid var(--color-border);
        border-top-color: var(--color-interactive);
        border-radius: 50%;
        animation: spin 700ms linear infinite;
    }

    .composer-dock {
        position: absolute;
        inset-inline: 0;
        bottom: 0;
        z-index: 1;
        padding-bottom: var(--space-3);
        padding-right: var(--scrollbar-gutter, 0px);
        /* Let wheel/click events in the gutters reach the chat behind the
           dock; the composer row and disclaimer stay interactive. */
        pointer-events: none;
    }

    /* The messages stay visible through the translucent composer card; only
       the very bottom (disclaimer strip) fades them out. Stops short of the
       scrollbar so it doesn't get tinted by the fade. */
    .composer-dock::before {
        content: '';
        position: absolute;
        left: 0;
        right: var(--scrollbar-gutter, 0px);
        bottom: 0;
        height: 5rem;
        z-index: -1;
        background: linear-gradient(to top, var(--color-surface-raised) 55%, transparent);
    }

    .composer-dock > * { pointer-events: auto; }

    /* Mirror a message row's grid (avatar column + content) so the composer
       card lines up exactly with the message text column. */
    .composer-row {
        display: grid;
        grid-template-columns: 2rem minmax(0, 1fr);
        gap: var(--space-3);
        width: min(100%, 52rem);
        margin-inline: auto;
        padding-inline: var(--space-5);
    }

    .composer-row :global(.chat-composer-wrapper) {
        grid-column: 2;
        max-width: none;
        min-width: 0;
    }

    .disclaimer {
        margin: var(--space-2) 0 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        text-align: center;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    @media (--bp-sm-and-smaller) {
        .messages, .composer-row { padding-inline: var(--space-3); }
        .messages { padding-top: var(--space-5); }
    }

    @media print {
        :global(.app-sidebar), .composer-dock, :global(.chat-page > header) { display: none !important; }
        .chat-page, .chat-body, .scroll-region { display: block; height: auto; overflow: visible; }
        .messages { padding-bottom: var(--space-5); }
    }
</style>
