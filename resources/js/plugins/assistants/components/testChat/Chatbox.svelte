<script lang="ts">
    import ChatLog from "./ChatLog.svelte";
    import ChatInput from "./ChatInput.svelte";
    import {provideChatConfig} from "./stream/chatConfig.svelte.js";
    import {provideChatStore} from "./stream/chatStore.svelte.js";
    import type {Assistant} from "$plugins/assistants/types/assistant";
    import type {Snippet} from "svelte";
    import type {ChatStoreApi} from "./stream/chatStore.svelte.js";

    interface Props {
        /** The assistant this chat talks to; reactive, so unsaved builder edits apply immediately. */
        assistant: Assistant;
        /** Optional header row; receives the chat so it can offer e.g. a reset. */
        header?: Snippet<[ChatStoreApi]>;
    }

    const {assistant, header}: Props = $props();

    // Chatbox is the context provider: it owns the chat + config state and
    // exposes it to children via the useChatStore() / useChatConfig() hooks.
    const config = provideChatConfig(() => assistant);
    const chat = provideChatStore(config);
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
