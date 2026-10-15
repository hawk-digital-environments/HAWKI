<!--
  @component Page component for the chat module's `/` index route (route name
  `chat.index`, see `ChatModule.ts`) — the "new chat" screen. Renders into the
  shared `ChatLayout`'s scroll region: the welcome hero, or the optimistic
  first message while its conversation is being created. The composer itself
  lives in the layout, so sending the first message keeps all of the user's
  composer settings as the navigation to `chat.conversation` happens.
-->
<script lang="ts">
    import ChatMessageView from '$plugins/core/modules/chat/components/ChatMessage.svelte';
    import ChatWelcome from '$plugins/core/modules/chat/components/ChatWelcome.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useChatComposerContext} from '$plugins/core/modules/chat/hooks/useChatComposerContext.svelte.js';

    // The index route matches without params; accept (and ignore) the route
    // props so the component satisfies the router's page signature.
    interface Props {
    }

    const {}: Props = $props();
    const app = useApp();
    const store = useStore('chat');
    const {__} = useTranslator();
    // Reads the layout's composer session (see the hook's docs).
    const chat = useChatComposerContext();

    // Opening this page always starts from a blank chat, also when coming
    // from an open conversation.
    $effect(() => {
        store.startNew();
    });

    // Land the cursor in the input so typing can start right away.
    $effect(() => {
        chat.autoFocusInput();
    });

    // Clicking the "new chat" button in the sidebar while this page is
    // already open should give the user an active input again.
    $effect(() => {
        return app.events.sync.on('onNewChatRequested', () => {
            chat.autoFocusInput();
        });
    });
</script>

{#if chat.pendingMessage}
    <div class="messages" role="log" aria-live="polite" aria-label={__('chat.page.messageHistory')}>
        <ChatMessageView
            message={chat.pendingMessage}
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

<style>
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

    /* Mobile: the floating nav toggle overlays the content top, so the
       message list reserves room to clear it. */
    @media (--bp-md-and-smaller) {
        .messages { padding-top: calc(var(--space-2_5) + var(--nav-row-h) + var(--space-2)); }
    }
</style>
