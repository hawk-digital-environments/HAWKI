<script lang="ts">
    import type {ChatMessage} from "../types";

    let {message} = $props<{ message: ChatMessage }>();
</script>

<div class="user-message">
    {#each message.parts as part, i (i)}
        {#if part.type === "text"}
            <span class="text">{part.text}</span>
        {/if}
    {/each}
</div>

<style>
    .user-message {
        padding: .5rem .75rem;
        background: var(--color-accent-fill);
        color: var(--color-on-accent-fill);
        border: var(--border);
        border-radius: var(--corner-md);
    }

    .text {
        white-space: pre-wrap;
        font-size: var(--font-size-sm);
        line-height: 1.5;
        word-break: break-word;
    }
</style>
