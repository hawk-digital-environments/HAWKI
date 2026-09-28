<script lang="ts">
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";
    import Button from "$lib/components/ui/button/Button.svelte";
    import ArrowUp02Icon from "$lib/components/ui/icons/iconset/ArrowUp02Icon.svelte";
    import {resizeTextarea, watchManualResize} from "./textarea-resizer";
    import {useChatStore} from "./stream/chatStore.svelte.js";
    import {useChatConfig} from "./stream/chatConfig.svelte.js";

    const chat = useChatStore();
    const config = useChatConfig();
    const hasModel = $derived(config.hasModel);

    const {__} = useTranslator();
    const HINT = $derived(__('assistants.builder.test.no_model'));
    const canSend = $derived(hasModel && value.trim() !== "" && chat.status !== "streaming");

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
        if (!text || !canSend) return;
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
    <div class="composer" class:disabled={!hasModel}>
        <textarea
            bind:this={inputField}
            bind:value
            id="chatbox-input"
            class="chatbox-input"
            placeholder={hasModel ? __('assistants.testChat.placeholder') : HINT}
            disabled={!hasModel}
            aria-label={__('assistants.testChat.placeholder')}
            rows="1"
            oninput={resize}
            onkeydown={handleKeydown}
        ></textarea>
        <Button
            class="send-btn"
            variant="accent"
            iconLeft={ArrowUp02Icon}
            aria-label={__('assistants.testChat.send')}
            disabled={!canSend}
            onclick={send}
        />
    </div>
</div>

<style>
    /* Mirrors the builder's step footer bar: same bottom inset, height,
       fill and pill shape, so both columns end on one line. */
    .chatlog-input-container {
        padding: 0 var(--space-4) var(--space-4);
    }

    .composer {
        --composer-inset: var(--space-1_5);
        --composer-control: 2.5rem;
        display: flex;
        align-items: flex-end;
        gap: var(--space-2);
        padding: var(--composer-inset) var(--composer-inset) var(--composer-inset) var(--space-5);
        border-radius: calc(var(--composer-control) / 2 + var(--composer-inset));
        background: var(--color-surface-light);
    }

    .chatbox-input {
        flex: 1;
        min-width: 0;
        box-sizing: border-box;
        background-color: transparent;
        /* One line is exactly the send button's height, so text and button
           share a centre line; grows up to max-height from there. */
        min-height: var(--composer-control);
        max-height: 10rem;
        padding: var(--space-2) 0;
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

    .composer.disabled .chatbox-input {
        cursor: not-allowed;
    }

    .composer :global(.send-btn) {
        flex-shrink: 0;
        width: var(--composer-control);
        height: var(--composer-control);
        border-radius: var(--corner-full);
    }
</style>
