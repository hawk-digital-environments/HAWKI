<script lang="ts">

    import SmileIcon from '$lib/components/ui/icons/iconset/SmileIcon.svelte';
    import Popover from '$lib/components/ui/popover/Popover.svelte';
    import {ActionIcon} from '$lib/components/ui/icons';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';

    import type { Snippet } from "svelte";

    const {__} = useTranslator();

    let {
        onSelect,
        ariaLabel,
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

    const triggerLabel = $derived(ariaLabel ?? __('ui.common.selectEmoji'));

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

    /**
     * What the web component's CSS variables can't reach (its search field
     * shares `--background` with the whole picker, the dividers are fixed),
     * injected into its shadow root the way the library documents. Colours
     * still come from the app tokens, which inherit through the shadow root.
     */
    const SHADOW_CSS = `
        /* One inset for every edge: the search pill sits --inset from the
           top, left and right, and the emoji glyphs line up with it (their
           buttons already pad them by --emoji-padding). --inset and
           --search-height come from the popover, which derives its corner
           radius from them. */
        .picker { font-family: inherit; }
        /* The library's own spacer above the search would double its top
           inset. */
        .pad-top { display: none; }
        .search-row { padding: var(--inset) var(--inset) calc(var(--inset) / 2); }
        /* The skin-tone toggle pads the right edge unevenly; the default
           tones are kept. */
        .skintone-button-wrapper { display: none; }
        .nav,
        .tabpanel { padding-inline: calc(var(--inset) - var(--emoji-padding)); }
        /* A reserved scrollbar gutter would widen the right edge only. */
        .tabpanel { scrollbar-gutter: auto; scrollbar-width: none; }
        .tabpanel::-webkit-scrollbar { display: none; }
        input.search {
            box-sizing: border-box;
            height: var(--search-height);
            background: var(--color-surface-light);
            border: none;
            border-radius: calc(var(--search-height) / 2);
            padding: 0 0.875rem;
        }
        input.search:focus { outline: 2px solid var(--color-focus-ring); outline-offset: 0; }
        .indicator-wrapper { border-bottom: none; }
        .indicator { border-radius: 9999px; }
        .nav-button { border-radius: 9999px; }
        .nav-button:hover { background: var(--button-hover-background); }
        .category {
            font-size: 0.75rem;
            color: var(--color-text-muted);
            padding: 0.75rem var(--emoji-padding) 0.25rem;
        }
        /* No favourites bar: most-used emojis add a second, redundant row. */
        .favorites { display: none; }
        button.emoji { transition: transform 300ms cubic-bezier(0.22, 1, 0.36, 1); }
        button.emoji:hover { transform: scale(1.15); }
        button.emoji:active { transform: scale(0.9); }
    `;

    function styleShadow(node: HTMLElement): void {
        const root = node.shadowRoot;
        if (!root || root.querySelector('style[data-hawki]')) return;
        const style = document.createElement('style');
        style.dataset.hawki = '';
        style.textContent = SHADOW_CSS;
        root.appendChild(style);
    }

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
            {#if trigger}
                <button
                    {...props}
                    type="button"
                    class="custom-trigger"
                    aria-label={triggerLabel}
                >
                    {@render trigger()}
                </button>
            {:else}
                <ActionIcon
                    {...props}
                    icon={SmileIcon}
                    label={triggerLabel}
                    size="md"
                    class="trigger"
                />
            {/if}
        {/snippet}

        {#snippet popover()}
            {#if loaded}
                <emoji-picker class={isDark ? "dark" : "light"} onemoji-click={handleEmojiClick} use:styleShadow></emoji-picker>
            {/if}
        {/snippet}
    </Popover>
</div>

<style>
    .emoji-picker {
        display: inline-flex;
    }

    .emoji-picker :global(.trigger) {
        color: var(--color-text-muted);
    }

    .emoji-picker :global(.custom-trigger) {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        color: var(--color-text-muted);
        border: none;
        padding: 0;
        cursor: pointer;
    }

    /* The shared Popover supplies a flat card (hairline border, no shadow);
       the web component sits flush inside it. The corners are concentric
       with the search pill: its radius plus the inset between them, plus
       the border the inset is measured inside of. */
    :global(.popover-content.emoji-popover) {
        --inset: 0.625rem;
        --search-height: 2.25rem;
        width: auto;
        padding: 0;
        border-radius: calc(var(--search-height) / 2 + var(--inset) + var(--divider-width));
        box-shadow: none;
        overflow: hidden;
    }

    /* Mapped onto the app tokens so both themes follow the app. */
    :global(.popover-content.emoji-popover) emoji-picker {
        --background: var(--color-surface-raised);
        --border-size: 0;
        --border-radius: 0;
        --input-font-color: var(--color-text);
        --input-placeholder-color: var(--color-text-muted);
        --input-font-size: var(--font-size-xs);
        --category-font-color: var(--color-text-muted);
        --indicator-color: var(--color-text);
        --indicator-height: 2px;
        --button-hover-background: var(--color-hover);
        --button-active-background: var(--color-highlight);
        --outline-color: var(--color-focus-ring);
        --emoji-size: 1.5rem;
        --emoji-padding: 0.375rem;
        --num-columns: 8;
        height: 22rem;
    }
</style>
