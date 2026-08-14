<script lang="ts">
    import UserMessage from "./messages/UserMessage.svelte";
    import AiMessage from "./messages/AiMessage.svelte";
    import {useChatStore} from "./stream/chatStore.svelte.js";

    const chat = useChatStore();
    const messages = $derived(chat.messages);

    let scroller = $state<HTMLDivElement | null>(null);

    $effect(() => {
        for (const m of messages) {
            void m.parts.length;
            for (const p of m.parts) {
                if (p.type === "text") void p.text;
            }
        }
        if (scroller) {
            scroller.scrollTop = scroller.scrollHeight;
        }
    });
</script>

<div class="chatlog" bind:this={scroller} class:empty={messages.length === 0}>
    {#if messages.length === 0}
        <p class="chatlog-empty">Starte eine Unterhaltung, um deinen Assistenten zu testen.</p>
    {/if}
    {#each messages as message, i (i)}
        {#if message.role === "user"}
            <UserMessage {message}/>
        {:else}
            <AiMessage {message}/>
        {/if}
    {/each}
</div>

<style>
    .chatlog {
        flex-grow: 1;
        min-height: 20rem;
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        padding: var(--space-4);
        overflow-y: auto;
    }

    /* Centre the hint while the log is empty. */
    .chatlog.empty {
        align-items: center;
        justify-content: center;
    }

    .chatlog-empty {
        margin: 0;
        max-width: 22rem;
        text-align: center;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
</style>
