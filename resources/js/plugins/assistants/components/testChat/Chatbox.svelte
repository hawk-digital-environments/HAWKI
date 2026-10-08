<script lang="ts">
    import ChatLog from "./ChatLog.svelte";
    import ChatInput from "./ChatInput.svelte";
    import {provideChatConfig, type ChatVariant} from "./stream/chatConfig.svelte.js";
    import {provideChatStore} from "./stream/chatStore.svelte.js";
    import type {Assistant} from "$plugins/assistants/types/assistant";
    import {untrack, type Snippet} from "svelte";
    import type {ChatStoreApi} from "./stream/chatStore.svelte.js";
    import {fade} from "svelte/transition";
    import {dragDrop} from "$plugins/assistants/actions/dragDrop.svelte.js";
    import FileDropHint from "$lib/components/ui/file-drop/FileDropHint.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";

    interface Props {
        /** The assistant this chat talks to; reactive, so unsaved builder edits apply immediately. */
        assistant: Assistant;
        /** Optional header row; receives the chat so it can offer e.g. a reset. */
        header?: Snippet<[ChatStoreApi]>;
        /** Which conversation this is; fixed for the lifetime of the chatbox. */
        variant?: ChatVariant;
        /**
         * A chat owned by the caller, so the conversation outlives this
         * component (and a `guide` chat can be passed in). Defaults to a
         * fresh test chat owned by the chatbox. Fixed for its lifetime.
         */
        chat?: ChatStoreApi;
    }

    const {assistant, header, variant = "test", chat: ownedChat}: Props = $props();

    // Chatbox is the context provider: it exposes the chat + config state to
    // children via the useChatStore() / useChatConfig() hooks.
    const config = provideChatConfig(() => assistant, untrack(() => variant));
    const chat = provideChatStore(config, untrack(() => ownedChat));

    const {__} = useTranslator();

    /** Files can be dropped anywhere on the chat when it takes uploads. */
    let dragging = $state(false);
    /** Same conditions as the composer's attach button. */
    const canDrop = $derived(chat.uploads !== undefined && chat.ready && !chat.uploads.busy);
    /** Why a drop would be refused, shown in place of the drop label. */
    const dropBlocked = $derived(chat.uploads?.blockedHint ?? null);

    const handleDrop = (files: FileList): void => {
        if (!chat.uploads || !canDrop || dropBlocked) return;
        void chat.uploads.add(Array.from(files));
    };
</script>

<div class="chatbox"
     use:dragDrop={{onDrop: handleDrop, onDragState: (state) => dragging = state !== "idle"}}>
    {@render header?.(chat)}
    <ChatLog/>
    <ChatInput/>
    {#if dragging && canDrop}
        <div class="chatbox-drop" transition:fade={{duration: 120}}>
            <FileDropHint label={dropBlocked ?? __('assistants.builder.guide.drop_label')}
                          blocked={dropBlocked !== null}/>
        </div>
    {/if}
</div>

<style>
    .chatbox {
        position: relative;
        display: flex;
        width: 100%;
        height: 100%;
        min-height: 10rem;
        flex-direction: column;
        overflow: hidden;
        background: var(--color-surface-raised);
        border: var(--border);
        border-radius: var(--corner-md);
    }

    /* Covers the whole chat while files hover over it; the chat's own
       surface, so the hint reads as the chat itself turning into a drop
       target rather than a layer on top. */
    .chatbox-drop {
        position: absolute;
        inset: 0;
        z-index: 2;
        background: var(--chatbox-drop-bg, var(--color-surface-raised));
        pointer-events: none;
    }
</style>
