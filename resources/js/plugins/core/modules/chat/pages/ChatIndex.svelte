<!--
@component Page component for the chat module's `/` index route (route name
`chat.index`, see `ChatModule.ts`) — the "new chat" screen. Shows the welcome
hero and a fresh composer. Sending the first message makes the `ChatTransport`
create the conversation and navigate to its `chat.conversation` route
(`ChatConversation.svelte`), where the still-running generation is picked up
from the store's in-flight cache.
-->
<script lang="ts">
    import ChatComposer from '$plugins/core/snippets/ChatComposer.svelte';
    import ChatComposerDock from '$plugins/core/modules/chat/components/ChatComposerDock.svelte';
    import ChatMessageView from '$plugins/core/modules/chat/components/ChatMessage.svelte';
    import ChatWelcome from '$plugins/core/modules/chat/components/ChatWelcome.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {ChatTransport} from '$plugins/core/modules/chat/transport/ChatTransport.js';
    import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ChatMessage} from '$plugins/core/modules/chat/types.js';

    // The index route matches without params; accept (and ignore) the route
    // props so the component satisfies the router's page signature.
    interface Props {
    }

    const {}: Props = $props();
    const app = useApp();
    const store = useStore('chat');
    const systemPromptStore = useStore('system-prompts');
    const router = useRouter();
    const {__} = useTranslator();
    const defaultPrompt = systemPromptStore.getPromptByType('default').prompt;
    const transport = new ChatTransport(app, store, {
        // Do not pull the user back if they switched chats while the
        // conversation was being created.
        onConversationCreated: createdSlug => {
            if (router.isActive('chat.index')) {
                void router.goToRoute('chat.conversation', {slug: createdSlug});
            }
        },
        onConversationPending: message => pendingMessage = message
    });

    let composer = $state<ComposerContext | null>(null);
    let pendingMessage = $state<ChatMessage | null>(null);
    let scrollRegion = $state<HTMLDivElement | null>(null);
    let composerDockHeight = $state(0);

    // No messages yet: welcome text and composer are centred as one block
    // instead of the composer docking to the bottom of the scroll region.
    const isEmpty = $derived(!pendingMessage);

    // Opening this page always starts from a blank chat, also when coming
    // from an open conversation.
    $effect(() => {
        store.startNew();
    });

    // Land the cursor in the input so typing can start right away.
    $effect(() => {
        if (composer) composer.focusInput();
    });
</script>

<section class="chat-page" style:--composer-dock-height="{composerDockHeight}px">
    <div class="chat-body" class:empty={isEmpty}>
        <div class="scroll-region" bind:this={scrollRegion}>
            {#if pendingMessage}
                <div class="messages" role="log" aria-live="polite" aria-label={__('chat.page.messageHistory')}>
                    <ChatMessageView
                        message={pendingMessage}
                        onDelete={() => undefined}
                        onDeleteAttachment={() => undefined}
                    />
                    <div class="pending-response" role="status">
                        <span class="spinner" aria-hidden="true"></span>
                        <span>{__('chat.page.generating')}</span>
                    </div>
                </div>
            {:else}
                <ChatWelcome />
            {/if}
        </div>

        <ChatComposerDock {scrollRegion} bind:height={composerDockHeight}>
            <ChatComposer
                context="aiConv"
                {transport}
                initialSystemPrompt={defaultPrompt}
                onImproveMessage={(message, systemPrompt) => transport.improveMessage(message, systemPrompt)}
                onReady={value => composer = value}
            />
        </ChatComposerDock>
    </div>
</section>

<style>
    .chat-page {
        display: grid;
        grid-template-rows: minmax(0, 1fr);
        height: 100%;
        min-height: 0;
        background: var(--color-surface-raised);
    }

    /* Shared canvas for the scroll region and the floating composer. */
    .chat-body {
        position: relative;
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

    .empty :global(.welcome) {
        height: auto;
        min-height: 0;
        padding-bottom: var(--space-6);
    }

    .empty :global(.composer-dock) { position: static; padding-bottom: 0; }
    .empty :global(.composer-dock::before) { display: none; }

    .scroll-region { height: 100%; overflow-y: auto; }

    .messages {
        display: flex;
        width: min(100%, 52rem);
        margin: 0 auto;
        padding: var(--space-8) var(--space-5) calc(var(--composer-dock-height, 0px) + var(--space-5));
        flex-direction: column;
        gap: var(--space-7);
    }

    .pending-response {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .spinner {
        width: 1rem;
        height: 1rem;
        border: 2px solid var(--color-border);
        border-top-color: var(--color-active-text);
        border-radius: var(--corner-full);
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    @media (--bp-sm-and-smaller) {
        .messages { padding-inline: var(--space-3); }
    }

    /* Mobile: the floating nav toggle overlays the content top, so the message
       list and the empty-state welcome reserve room to clear it (toggle inset +
       height + gap) while still letting content scroll up under the
       SidebarContent fade overlay. */
    @media (--bp-md-and-smaller) {
        .messages { padding-top: calc(var(--space-2_5) + var(--nav-row-h) + var(--space-2)); }
        .empty :global(.welcome) { padding-top: calc(var(--space-2_5) + var(--nav-row-h) + var(--space-2)); }
    }

    @media print {
        :global(.app-sidebar) { display: none !important; }
        .chat-page, .chat-body, .scroll-region { display: block; height: auto; overflow: visible; }
    }
</style>
