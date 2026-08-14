<script lang="ts">
    import type {ChatMessage} from "../types";
    import TextPart from "./parts/TextPart.svelte";
    import ReasoningPart from "./parts/ReasoningPart.svelte";
    import ToolCallPart from "./parts/ToolCallPart.svelte";
    import ToolResultPart from "./parts/ToolResultPart.svelte";

    let {message} = $props<{ message: ChatMessage }>();
</script>

<div class="ai-message">
    {#each message.parts as part, i (i)}
        {#if part.type === "text"}
            <TextPart text={part.text}/>
        {:else if part.type === "reasoning"}
            <ReasoningPart text={part.text}/>
        {:else if part.type === "tool-call"}
            <ToolCallPart toolName={part.toolName} input={part.input}/>
        {:else if part.type === "tool-result"}
            <ToolResultPart toolName={part.toolName} output={part.output}/>
        {/if}
    {/each}
    {#if message.streaming && message.parts.length === 0}
        <span class="pending">…</span>
    {/if}
</div>

<style>
    .ai-message {
        padding: .5rem .75rem;
        background: var(--message-ai-bg);
        border: var(--border-stroke-thin);
        border-radius: var(--border-radius-normal);
    }

    .pending {
        color: var(--text-faded-color);
    }
</style>
