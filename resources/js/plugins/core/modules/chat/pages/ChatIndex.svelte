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
    let previousConversationSlug: string | null = null;
    let previousMessageCount = 0;
    let keepScrolledToBottom = false;
    let liveAnnouncement = $state('');
    let announcementConversationSlug: string | null = null;
    let wasGenerating = false;

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
        observer.observe(messages);
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

<section class="chat-page">
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
        />
    {:else}
        <header class="new-header"><span>{__('chat.page.newChat')}</span></header>
    {/if}

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
        <div class="composer-dock">
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
            <p class="disclaimer">{__('chat.page.disclaimer')}</p>
        </div>
    {/if}
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
        grid-template-rows: auto minmax(0, 1fr) auto;
        height: 100%;
        min-height: 0;
        background: var(--color-surface-raised);
    }

    .new-header {
        display: flex;
        min-height: 3.75rem;
        align-items: center;
        padding: var(--space-2) var(--space-5);
        border-bottom: var(--divider);
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-semibold);
    }

    .scroll-region { min-height: 0; overflow-y: auto; }

    .messages {
        display: flex;
        width: min(100%, 52rem);
        margin: 0 auto;
        padding: var(--space-8) var(--space-5) var(--space-5);
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

    h1 { margin: 0 0 var(--space-2); font-size: var(--font-size-xl); }
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
        padding: var(--space-2) var(--space-5) var(--space-3);
        background: linear-gradient(to top, var(--color-surface-raised) 72%, transparent);
    }

    .disclaimer {
        margin: var(--space-2) 0 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        text-align: center;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    @media (--bp-md-and-smaller) {
        .new-header {
            padding-right: var(--space-3);
            padding-left: calc(var(--space-3) + 2.75rem);
        }
    }

    @media (max-width: 640px) {
        .new-header, .messages, .composer-dock { padding-inline: var(--space-3); }
        .new-header { padding-left: calc(var(--space-3) + 2.75rem); }
        .messages { padding-top: var(--space-5); }
    }

    @media print {
        :global(.app-sidebar), .composer-dock, .new-header, :global(.chat-page > header) { display: none !important; }
        .chat-page, .scroll-region { display: block; height: auto; overflow: visible; }
    }
</style>
