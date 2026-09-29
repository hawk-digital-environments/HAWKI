<script lang="ts">
    import type {ChatMessage} from "../types";
    import Attachment01Icon from "$lib/components/ui/icons/iconset/Attachment01Icon.svelte";

    let {message} = $props<{ message: ChatMessage }>();
</script>

<!-- Same bubble as the main chat's user messages (core ChatMessage.svelte),
     pinned to the right edge. -->
<div class="user-message">
    {#each message.attachments ?? [] as name (name)}
        <span class="attachment"><Attachment01Icon size="1em"/><span class="attachment-name">{name}</span></span>
    {/each}
    {#each message.parts as part, i (i)}
        {#if part.type === "text"}
            <p class="text">{part.text}</p>
        {/if}
    {/each}
</div>

<style>
    .user-message {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        align-self: flex-end;
        max-width: 85%;
        padding: var(--space-2_5) var(--space-3);
        border-radius: var(--corner-lg);
        background: var(--color-surface-light);
        color: var(--color-text);
    }

    .attachment {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1_5);
        min-width: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    .attachment-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .text {
        margin: 0;
        white-space: pre-wrap;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        overflow-wrap: anywhere;
    }
</style>
