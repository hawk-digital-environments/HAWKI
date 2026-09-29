<script lang="ts">
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";
    import Button from "$lib/components/ui/button/Button.svelte";
    import ButtonWithTooltip from "$lib/components/ui/button/ButtonWithTooltip.svelte";
    import ArrowUp02Icon from "$lib/components/ui/icons/iconset/ArrowUp02Icon.svelte";
    import AttachmentIcon from "$lib/components/ui/icons/iconset/AttachmentIcon.svelte";
    import StarterPrompts from "$lib/components/ui/starter-prompts/StarterPrompts.svelte";
    import {resizeTextarea, watchManualResize} from "./textarea-resizer";
    import {useChatStore} from "./stream/chatStore.svelte.js";
    import {useChatConfig} from "./stream/chatConfig.svelte.js";

    const chat = useChatStore();
    const config = useChatConfig();
    const ready = $derived(chat.ready);
    const uploads = chat.uploads;

    let value = $state("");

    const {__} = useTranslator();
    const placeholder = $derived(
        config.variant === "guide"
            ? __('assistants.builder.guide.placeholder')
            : ready ? __('assistants.testChat.placeholder') : __('assistants.builder.test.no_model')
    );
    const canSend = $derived(ready && chat.status !== "streaming" && value.trim() !== "");

    let fileInput = $state<HTMLInputElement | null>(null);

    /**
     * Guide only, until the first message: a mix of "do it for me" and
     * questions, so it's clear the guide does both. Picking one sends it.
     */
    const starters = $derived(
        config.variant === "guide" && chat.messages.length === 0
            ? [
                __('assistants.builder.guide.starters.build'),
                __('assistants.builder.guide.starters.prompt'),
                __('assistants.builder.guide.starters.model'),
                __('assistants.builder.guide.starters.knowledge'),
            ]
            : []
    );

    let inputField = $state<HTMLTextAreaElement | null>(null);
    let minHeight = $state<number | null>(null);

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
        if (!canSend) return;
        chat.send(text);
        value = "";
        requestAnimationFrame(resize);
    };

    const handleFileChange = (e: Event): void => {
        const input = e.currentTarget as HTMLInputElement;
        if (input.files?.length) {
            void uploads?.add(Array.from(input.files));
            input.value = ""; // reset so the same file can be picked again
        }
    };

    const handleKeydown = (e: KeyboardEvent): void => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    };
</script>

<div class="chatlog-input-container">
    {#if starters.length > 0}
        <div class="starters">
            <StarterPrompts layout="list" prompts={starters} onselect={(prompt) => chat.send(prompt)}
                            disabled={!ready || chat.status === "streaming"}
                            aria-label={__('assistants.builder.guide.starters.label')}/>
        </div>
    {/if}
    <div class="composer" class:disabled={!ready} class:with-attach={uploads}>
        {#if uploads}
            <input type="file" multiple hidden accept={uploads.accept}
                   bind:this={fileInput} onchange={handleFileChange}/>
            <ButtonWithTooltip
                class="attach-btn"
                variant="iconGhost"
                iconLeft={AttachmentIcon}
                tooltip={uploads.blockedHint ?? __('assistants.builder.guide.attach')}
                aria-label={__('assistants.builder.guide.attach')}
                disabled={!ready || uploads.busy || uploads.blockedHint !== null}
                onclick={() => fileInput?.click()}
            />
        {/if}
        <textarea
            bind:this={inputField}
            bind:value
            class="chatbox-input"
            {placeholder}
            disabled={!ready}
            aria-label={placeholder}
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

    /* The attach button mirrors the send button's inset on the left. */
    .composer.with-attach {
        padding-left: var(--composer-inset);
    }

    .composer :global(.attach-btn) {
        flex-shrink: 0;
        width: var(--composer-control);
        height: var(--composer-control);
        border-radius: var(--corner-full);
    }

    .starters {
        margin-bottom: var(--space-3);
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
