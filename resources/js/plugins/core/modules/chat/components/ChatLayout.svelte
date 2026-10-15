<!--
  @component Shared route layout of the chat module — keeps the composer alive
  across the `chat.index` <-> `chat.conversation` navigation. Registered as the
  `lazyLayout` of both routes in `ChatModule.ts` with the same loader constant,
  so the router resolves it to the same component reference and never remounts
  it while the user moves between the two pages (see `RouterView`).

  Owns everything both pages duplicated: the `Page` shell with the
  conversation header (rendered only on the conversation route), the
  `.chat-body` canvas with its scroll region and the floating composer dock,
  and — through `useChatComposerContext()` — the composer context itself. The
  pages render into the scroll region via `children` and read the layout's
  DOM/measurements through `ChatLayoutContext`.
-->
<script lang="ts">
    import type {Route} from 'universal-router';
    import type {Snippet} from 'svelte';
    import ChatComposer from '$plugins/core/snippets/ChatComposer.svelte';
    import ChatComposerDock from '$plugins/core/modules/chat/components/ChatComposerDock.svelte';
    import ChatHeader from '$plugins/core/modules/chat/components/ChatHeader.svelte';
    import Page from '$lib/components/ui/page/Page.svelte';
    import PageHeaderBar from '$lib/components/ui/page/PageHeaderBar.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useRouter} from '$lib/components/ui/routing/index.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useChatComposerContext} from '$plugins/core/modules/chat/hooks/useChatComposerContext.svelte.js';
    import {createChatLayoutContext} from '$plugins/core/modules/chat/components/ChatLayoutContext.svelte.js';
    import {exportConversation, toConversationExportLabels} from '$plugins/core/modules/chat/utils/exportConversation.js';

    interface Props {
        /** The currently open chat page (`ChatIndex` or `ChatConversation`). */
        children: Snippet;
        /** The matched route; identifies which of the two pages is open. */
        route?: Route | null;
    }

    const {children, route}: Props = $props();

    const store = useStore('chat');
    const router = useRouter();
    const toast = useToastContext();
    const {getTranslationsFlat} = useTranslator();

    const chat = useChatComposerContext();
    const layout = createChatLayoutContext();

    const exportLabels = $derived(toConversationExportLabels(getTranslationsFlat('chat.export')));
    const onConversation = $derived(route?.name === 'chat.conversation');

    const isNewChat = $derived(onConversation
        ? !store.loading && !store.error && (!store.active || store.active.messages.length === 0)
        : !chat.pendingMessage);

    async function removeConversation() {
        if (!store.active) return;
        try {
            await store.remove(store.active.slug);
            void router.goToRoute('chat.index');
        } catch (error) {
            toast.error(error instanceof Error ? error.message : String(error));
        }
    }

    $effect(() => {
        // Hold the composer in its busy state while a response is streaming on
        // the conversation page
        chat.setFocus(onConversation ? store.isGenerating(store.active?.slug) : false);
    });
</script>

{#snippet headerBar()}
    {#if store.active}
        <ChatHeader
            conversation={store.active}
            generating={store.isGenerating(store.active.slug)}
            onRename={name => store.rename(store.active!.slug, name)}
            onDelete={removeConversation}
            onExport={format => {
                if (store.active) return exportConversation(store.active, format, exportLabels);
            }}
            onSkipToComposer={() => chat.focusInput()}
        />
    {:else}
        <!-- Keeps the header row of the page grid while the conversation is
             still loading or failed to load. -->
        <PageHeaderBar spacer />
    {/if}
{/snippet}

{#snippet pageBody()}
    <div
        class="chat-body"
        class:empty={isNewChat}
        style:--composer-dock-height="{layout.dockHeight}px"
        style:--scroll-region-height="{layout.regionHeight}px"
    >
        <div class="scroll-region" bind:this={layout.scrollRegion} bind:clientHeight={layout.regionHeight}>
            {@render children()}
        </div>

        <ChatComposerDock scrollRegion={layout.scrollRegion} bind:height={layout.dockHeight}>
            <ChatComposer context="aiConv"/>
        </ChatComposerDock>
    </div>
{/snippet}

<Page header={onConversation ? headerBar : undefined} body={pageBody}/>

<style>
    /* Shared canvas for the scroll region and the floating composer: the
       messages scroll behind the docked composer box. Fills the Page shell's
       body area. */
    .chat-body {
        position: relative;
        height: 100%;
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

    /* Mobile: the floating nav toggle overlays the content top, so the
       empty-state welcome reserves room to clear it (toggle inset +
       height + gap) while still letting content scroll up under the
       SidebarContent fade overlay. */
    @media (--bp-md-and-smaller) {
        .empty :global(.welcome) { padding-top: calc(var(--space-2_5) + var(--nav-row-h) + var(--space-2)); }
    }

    @media print {
        .chat-body, .scroll-region { display: block; height: auto; overflow: visible; }
    }
</style>
