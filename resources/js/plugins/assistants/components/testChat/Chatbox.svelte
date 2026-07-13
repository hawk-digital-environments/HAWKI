<script lang="ts">
    import ChatLog from "./ChatLog.svelte";
    import ChatInput from "./ChatInput.svelte";
    import {provideChatConfig} from "./stream/chatConfig.svelte.js";
    import {provideChatStore} from "./stream/chatStore.svelte.js";
    import type {Assistant} from "$plugins/assistants/types/assistant";

    interface Props {
        /** The assistant this chat talks to; reactive, so unsaved builder edits apply immediately. */
        assistant: Assistant;
    }

    const {assistant}: Props = $props();

    // Chatbox is the context provider: it owns the chat + config state and
    // exposes it to children via the useChatStore() / useChatConfig() hooks.
    const config = provideChatConfig(() => assistant);
    provideChatStore(config);
</script>

<div class="chatbox">
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
