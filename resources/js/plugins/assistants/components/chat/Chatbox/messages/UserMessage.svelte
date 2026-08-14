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
        background: var(--accent-color);
        color: var(--invert-text-color);
        border: var(--border-stroke-thin);
        border-radius: var(--border-radius-normal);
    }

    .text {
        white-space: pre-wrap;
        font-size: var(--font-sm);
        line-height: 1.5;
        word-break: break-word;
    }
</style>
