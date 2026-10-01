<script lang="ts">
    import type {ChatMessage} from "../types";
    import IngestPart from "./parts/IngestPart.svelte";

    let {message} = $props<{ message: ChatMessage }>();
</script>

<!-- Same bubble as the main chat's user messages (core ChatMessage.svelte),
     pinned to the right edge. Files the creator added show as their upload
     card instead. -->
{#each message.parts as part, i (i)}
    {#if part.type === "text"}
        <div class="user-message">
            <p class="text">{part.text}</p>
        </div>
    {:else if part.type === "ingest"}
        <div class="user-ingest">
            <IngestPart files={part.files}/>
        </div>
    {/if}
{/each}

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

    .user-ingest {
        display: flex;
        justify-content: flex-end;
        align-self: flex-end;
        max-width: 85%;
    }

    .text {
        margin: 0;
        white-space: pre-wrap;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        overflow-wrap: anywhere;
    }
</style>
