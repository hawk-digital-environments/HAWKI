<script lang="ts">
    import type {ChatMessage} from "../types";
    import TextPart from "./parts/TextPart.svelte";
    import ReasoningPart from "./parts/ReasoningPart.svelte";
    import ToolCallPart from "./parts/ToolCallPart.svelte";
    import AppliedPart from "./parts/AppliedPart.svelte";
    import ShimmerText from "$lib/components/ui/shimmer-text/ShimmerText.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";

    let {message} = $props<{ message: ChatMessage }>();

    const {__} = useTranslator();
</script>

<!-- Unboxed, like the main chat's assistant messages: the reply is plain
     text on the panel surface; only the user's turns sit in bubbles. -->
<div class="ai-message" aria-busy={message.streaming}>
    {#each message.parts as part, i (i)}
        {#if part.type === "text"}
            <TextPart text={part.text} streaming={message.streaming}/>
        {:else if part.type === "reasoning"}
            <ReasoningPart text={part.text} active={message.streaming && i === message.parts.length - 1}/>
        {:else if part.type === "tool-call"}
            <ToolCallPart name={part.name}/>
        {:else if part.type === "applied"}
            <AppliedPart labels={part.labels}/>
        {/if}
    {/each}
    {#if message.streaming && message.parts.length === 0}
        <p class="pending" role="status"><ShimmerText>{__('chat.page.thinking')}</ShimmerText></p>
    {/if}
</div>

<style>
    .ai-message {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        min-width: 0;
    }

    .pending {
        margin: 0;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
    }
</style>
