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
    import ChatWelcome from '$plugins/core/modules/chat/components/ChatWelcome.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {ChatTransport} from '$plugins/core/modules/chat/transport/ChatTransport.js';
    import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';

    // The index route matches without params; accept (and ignore) the route
    // props so the component satisfies the router's page signature.
    interface Props {
    }

    const {}: Props = $props();
    const app = useApp();
    const store = useStore('chat');
    const router = useRouter();
    const {__} = useTranslator();
    const defaultPrompt = app.stores.get('system-prompts').getPromptByType('default')?.prompt ?? '';
    const transport = new ChatTransport(app, store, {
        // Do not pull the user back if they switched chats while the
        // conversation was being created.
        onConversationCreated: createdSlug => {
            if (router.isActive('/chat')) void router.goTo(router.p(`/chat/${createdSlug}`));
        }
    });

    let composer = $state<ComposerContext | null>(null);
    let scrollRegion = $state<HTMLDivElement | null>(null);
    let composerDockHeight = $state(0);

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
    <header class="new-header"><span>{__('chat.page.newChat')}</span></header>

    <div class="chat-body">
        <div class="scroll-region" bind:this={scrollRegion}>
            <ChatWelcome />
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
        grid-template-rows: auto minmax(0, 1fr);
        height: 100%;
        min-height: 0;
        background: var(--color-surface-raised);
    }

    /* Shared canvas for the scroll region and the floating composer. */
    .chat-body {
        position: relative;
        min-height: 0;
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

    .scroll-region { height: 100%; overflow-y: auto; }

    @media (--bp-md-and-smaller) {
        .new-header {
            padding-right: var(--space-3);
            padding-left: calc(var(--space-3) + 2.75rem);
        }
    }

    @media (max-width: 640px) {
        .new-header {
            padding-inline: var(--space-3);
            padding-left: calc(var(--space-3) + 2.75rem);
        }
    }

    @media print {
        :global(.app-sidebar), .new-header { display: none !important; }
        .chat-page, .chat-body, .scroll-region { display: block; height: auto; overflow: visible; }
    }
</style>
