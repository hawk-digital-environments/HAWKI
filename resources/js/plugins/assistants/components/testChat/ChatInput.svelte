<script lang="ts">
    import Button from "$lib/components/ui/button/Button.svelte";
    import SentIcon from "$lib/components/ui/icons/iconset/SentIcon.svelte";
    import {resizeTextarea, watchManualResize} from "./textarea-resizer";
    import {useChatStore} from "./stream/chatStore.svelte.js";
    import {useChatConfig} from "./stream/chatConfig.svelte.js";

    const chat = useChatStore();
    const config = useChatConfig();
    const hasModel = $derived(config.hasModel);

    const HINT = "Kein Modell ausgewählt — wähle ein Modell aus, um den Assistenten zu testen.";

    let inputField = $state<HTMLTextAreaElement | null>(null);
    let minHeight = $state<number | null>(null);
    let value = $state("");

    $effect(() => {
        if (!inputField) return;
        resize();
        return watchManualResize(inputField, (newMinHeight: number) => {
            minHeight = newMinHeight;
        });
    });

    const resize = (): void => {
        if (!inputField) return;
        minHeight = resizeTextarea(inputField, minHeight, true);
    };

    const send = (): void => {
        const text = value.trim();
        if (!text || !hasModel) return;
        chat.send(text);
        value = "";
        requestAnimationFrame(resize);
    };

    const handleKeydown = (e: KeyboardEvent): void => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    };
</script>

<div class="chatlog-input-container">
    {#if !hasModel}
        <p class="handle-hint">{HINT}</p>
    {/if}
    <textarea
        bind:this={inputField}
        bind:value
        id="chatbox-input"
        class="chatbox-input"
        placeholder={hasModel ? "Nachricht schreiben…" : ""}
        disabled={!hasModel}
        title={hasModel ? "" : HINT}
        rows="1"
        oninput={resize}
        onkeydown={handleKeydown}
    ></textarea>
    <div class="send-btn-wrapper" title={hasModel ? "" : HINT}>
        <Button
            variant="fill"
            iconLeft={SentIcon}
            disabled={!hasModel}
            onclick={send}
        />
    </div>
</div>

<style>
    .chatlog-input-container {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: end;
        gap: var(--space-2);
        padding: var(--space-2);
        border-top: var(--divider);
    }

    .chatbox-input {
        background-color: transparent;
        /* Size to content: a single-row base that grows up to max-height,
           rather than a giant fixed block. */
        min-height: 2.5rem;
        max-height: 10rem;
        padding: var(--space-2) var(--space-3);
        border: none;
        outline: none;
        resize: none;
        font-family: inherit;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text);
    }

    .chatbox-input::placeholder {
        color: var(--color-text-muted);
    }

    .handle-hint {
        grid-column: 1 / -1;
        margin: 0;
        padding: var(--space-1) var(--space-3);
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }

    .send-btn-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>
