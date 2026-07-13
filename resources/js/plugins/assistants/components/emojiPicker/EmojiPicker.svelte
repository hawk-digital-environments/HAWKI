<script lang="ts">

    import SmileIcon from '$lib/components/ui/icons/iconset/SmileIcon.svelte';
    import Popover from '$lib/components/ui/popover/Popover.svelte';

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
        align?: "start" | "center" | "end";
        /** Vertical side the popover opens toward, relative to the trigger. */
        side?: "top" | "right" | "bottom" | "left";
        /** Optional custom trigger content; defaults to a mood icon. */
        trigger?: Snippet;
    } = $props();

    let open = $state(false);
    let loaded = $state(false);
    let isDark = $state(false);

    // Lazy-load the ~1MB library when the picker is first opened, and pick
    // the web component's theme. Dark mode used to be detected via the
    // trigger's `.darkMode` ancestor; with the portalled shared Popover the
    // trigger element is no longer reachable, so check globally — the mode
    // is app-wide anyway.
    $effect(() => {
        if (!open) return;
        if (!loaded) {
            import("emoji-picker-element").then(() => {
                loaded = true;
            });
        }
        isDark = !!document.querySelector(".darkMode");
    });

    function handleEmojiClick(e: CustomEvent<{ unicode?: string }>) {
        const unicode = e.detail?.unicode;
        if (!unicode) return;
        onSelect(unicode);
        open = false;
    }
</script>

<div class="emoji-picker">
    <Popover bind:open {side} {align} contentProps={{class: "emoji-popover"}}>
        {#snippet children({props})}
            <button
                {...props}
                type="button"
                class="trigger"
                aria-label={ariaLabel}
            >
                {#if trigger}
                    {@render trigger()}
                {:else}
                    <SmileIcon size="1em" />
                {/if}
            </button>
        {/snippet}

        {#snippet popover()}
            {#if loaded}
                <emoji-picker class={isDark ? "dark" : "light"} onemoji-click={handleEmojiClick}></emoji-picker>
            {/if}
        {/snippet}
    </Popover>
</div>

<style>
    .emoji-picker {
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

    /* Neutralize the shared Popover's fixed-width card chrome: the
       emoji-picker web component brings its own surface, border and size. */
    :global(.popover-content.emoji-popover) {
        width: auto;
        padding: 0;
        border: none;
        background: transparent;
        box-shadow: none;
        overflow: hidden;
    }

    :global(.popover-content.emoji-popover) emoji-picker {
        --border-radius: var(--corner-sm);
        --input-border-radius: var(--corner-xs);
        --input-padding: 0.375rem 0.75rem;
        --input-border-color: var(--color-border);
        --input-border-size: 1.5px;
    }
</style>
