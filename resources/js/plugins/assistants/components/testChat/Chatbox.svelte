<script lang="ts">
    import ChatLog from "./ChatLog.svelte";
    import ChatInput from "./ChatInput.svelte";
    import {provideChatConfig, type ChatVariant} from "./stream/chatConfig.svelte.js";
    import {provideChatStore} from "./stream/chatStore.svelte.js";
    import type {Assistant} from "$plugins/assistants/types/assistant";
    import {untrack, type Snippet} from "svelte";
    import type {ChatStoreApi} from "./stream/chatStore.svelte.js";

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
</script>

<div class="chatbox">
    {@render header?.(chat)}
    <ChatLog/>
    <ChatInput/>
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
</style>
