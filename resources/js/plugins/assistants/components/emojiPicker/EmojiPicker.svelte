<script lang="ts">

    import SmileIcon from '$lib/components/ui/icons/iconset/SmileIcon.svelte';

    import type { Snippet } from "svelte";

    let {
        onSelect,
        ariaLabel = "Emoji auswählen",
        align = "end",
        side = "bottom",
        trigger,
    }: {
        /** Called with the selected emoji's unicode character. */
        onSelect: (emoji: string) => void;
        ariaLabel?: string;
        /** Horizontal edge the popover aligns to, relative to the trigger. */
        align?: "start" | "end";
        /** Vertical side the popover opens toward, relative to the trigger. */
        side?: "top" | "bottom";
        /** Optional custom trigger content; defaults to a mood icon. */
        trigger?: Snippet;
    } = $props();

    let open = $state(false);
    let loaded = $state(false);
    let isDark = $state(false);
    let triggerEl = $state<HTMLButtonElement>();
    let popoverEl = $state<HTMLDivElement>();

    async function toggle() {
        if (!loaded) {
            // Lazy-load the ~1MB library only when the picker is first opened.
            await import("emoji-picker-element");
            loaded = true;
        }
        if (!open) {
            isDark = !!triggerEl?.closest(".darkMode");
        }
        open = !open;
    }

    function handleEmojiClick(e: CustomEvent<{ unicode?: string }>) {
        const unicode = e.detail?.unicode;
        if (!unicode) return;
        onSelect(unicode);
        open = false;
    }

    function handlePointerDown(e: PointerEvent) {
        if (!open) return;
        const target = e.target as Node;
        if (triggerEl?.contains(target) || popoverEl?.contains(target)) return;
        open = false;
    }

    function handleKeydown(e: KeyboardEvent) {
        if (open && e.key === "Escape") {
            open = false;
            triggerEl?.focus();
        }
    }
</script>

<svelte:window onpointerdown={handlePointerDown} onkeydown={handleKeydown} />

<div class="emoji-picker">
    <button
        bind:this={triggerEl}
        type="button"
        class="trigger"
        aria-label={ariaLabel}
        aria-haspopup="dialog"
        aria-expanded={open}
        onclick={toggle}
    >
        {#if trigger}
            {@render trigger()}
        {:else}
            <SmileIcon size="1em" />
        {/if}
    </button>

    {#if open}
        <div
            bind:this={popoverEl}
            class="popover"
            class:align-start={align === "start"}
            class:side-top={side === "top"}
            role="dialog"
            aria-label={ariaLabel}
        >
            <emoji-picker class={isDark ? "dark" : "light"} onemoji-click={handleEmojiClick}></emoji-picker>
        </div>
    {/if}
</div>

<style>
    .emoji-picker {
        position: relative;
        display: inline-flex;
    }

    .trigger {
        display: inline-flex;
        height: 2.5rem;
        width: 2.5rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        background: transparent;
        color: var(--color-text-muted);
        transition: color var(--duration-fast, 0.15s) ease;
    }

    .trigger:hover {
        color: var(--color-text);
    }

    .popover {
        position: absolute;
        top: calc(100% + 0.375rem);
        right: 0;
        z-index: 50;
        border-radius: var(--corner-sm);
        overflow: hidden;
    }

    .popover.align-start {
        right: auto;
        left: 0;

    }

    .popover.side-top {
        top: auto;
        bottom: calc(100% + 0.375rem);
    }

    .emoji-picker {
        --background: var(--bg-color);
        --border-radius: var(--corner-sm);
        --input-border-radius: var(--corner-xs);
        --input-padding: 0.375rem 0.75rem;
        --input-border-color: var(--color-border);
        --input-border-size: 1.5px;
    }
</style>
