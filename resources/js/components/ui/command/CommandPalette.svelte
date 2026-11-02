<script module lang="ts">
    import type {CommandItemDefinition} from './CommandResults.svelte';

    export type {CommandItemDefinition};
</script>

<!--
  @component Command palette anchored to its own trigger. Arrow keys / Enter
  drive the selection, and an optional ⌘K (Ctrl+K) shortcut opens it from
  anywhere.

  It is a Popover rather than a centred modal so it can double as an in-place
  dropdown: the trigger keeps its `data-state` while open, so callers can style
  it as one connected control. On small screens it opens as a BottomSheet instead.
-->
<script lang="ts">
    import {
        Command as CommandPrimitive,
        Popover as PopoverPrimitive,
        mergeProps,
        type PopoverContentProps
    } from 'bits-ui';
    import type {Snippet} from 'svelte';
    import CommandResults, {type CommandGroupDefinition} from './CommandResults.svelte';
    import BottomSheet from '$lib/components/ui/sheet/BottomSheet.svelte';
    import {useBreakpoint} from '$lib/components/util/breakpoints/useBreakpoint.svelte.js';
    import {isApple} from '$lib/utils/platform.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** Rows to show, in display order. */
        items: CommandItemDefinition[];
        /** Whether the palette is open. Supports bind:open. */
        open?: boolean;
        /** Value marked as the current one — renders a trailing check. */
        current?: string;
        /** Fired with the chosen row's `value`. The palette closes itself first. */
        onSelect?: (value: string) => void;
        /** Screen-reader name for the palette. Defaults to the translated `ui.commandPalette.label`. */
        label?: string;
        /** Binds ⌘K / Ctrl+K to toggle the palette. */
        shortcut?: boolean;
        /**
         * The control that opens the palette. Receives `props` that MUST be
         * spread onto the trigger element, the current `open` state, and the
         * platform-correct shortcut keys (e.g. `['⌘', 'K']`) so the trigger can
         * advertise the shortcut without repeating the platform check.
         */
        trigger: Snippet<[{props: Record<string, any>; open: boolean; shortcutKeys: string[]}]>;
        /** Props forwarded to the floating content (side/align/offset/class). */
        contentProps?: PopoverContentProps;
    }

    let {
        items,
        open = $bindable(false),
        current,
        onSelect,
        label,
        shortcut = true,
        trigger,
        contentProps
    }: Props = $props();

    const {__} = useTranslator();
    const paletteLabel = $derived(label ?? __('ui.commandPalette.label'));

    const breakpoint = useBreakpoint();
    const asSheet = $derived(breakpoint.is('bpMdAndSmaller'));

    /** Rows bucketed under their heading, in first-seen order. */
    const groups = $derived.by((): CommandGroupDefinition[] => {
        const buckets = new Map<string, CommandItemDefinition[]>();
        for (const item of items) {
            const key = item.groupLabel ?? '';
            const bucket = buckets.get(key);
            if (bucket) bucket.push(item);
            else buckets.set(key, [item]);
        }
        return Array.from(buckets, ([groupLabel, groupItems]) => ({
            id: groupLabel,
            label: groupLabel || undefined,
            items: groupItems
        }));
    });

    // ⌘ on Apple platforms, Ctrl elsewhere.
    const shortcutKeys = isApple ? ['⌘', 'K'] : ['Strg', 'K'];

    // Focus target when the palette opens. Command binds its arrow/Enter
    // handling to the *root* (which carries tabindex="-1"), so focusing the root
    // is what makes the list keyboard-drivable now that there is no input.
    let rootEl = $state<HTMLElement | null>(null);

    // Command always keeps one row `data-selected` so Enter has a target, which
    // makes the first row look hovered the moment the palette opens. The
    // keyboard wash is only painted once the user actually arrows into the list.
    let armed = $state(false);

    // ── Proximity hover ──────────────────────────────────────────────────
    // Same behaviour as the sidebar's nav: one highlight glides to whichever
    // row the pointer is nearest, rather than each row lighting up on its own
    // :hover. Command doesn't move its selection on hover, so this is tracked
    // here from the rows' measured positions.

    let viewportEl = $state<HTMLElement | null>(null);
    let hoverRect = $state<{top: number; height: number} | null>(null);

    $effect(() => {
        if (!open) {
            armed = false;
            hoverRect = null;
        }
    });

    function trackPointer(event: PointerEvent) {
        const viewport = viewportEl;
        if (!viewport) return;
        // Measured per move rather than registered up front, so the rows can
        // change without any bookkeeping here.
        const rows = viewport.querySelectorAll<HTMLElement>('.command-item:not([data-disabled])');
        const base = viewport.getBoundingClientRect();
        let best: {top: number; height: number} | null = null;
        let bestDist = Infinity;
        for (const row of rows) {
            const r = row.getBoundingClientRect();
            const center = r.top + r.height / 2;
            const inside = event.clientY >= r.top && event.clientY <= r.bottom;
            const dist = inside ? 0 : Math.abs(event.clientY - center);
            if (dist < bestDist) {
                bestDist = dist;
                best = {top: r.top - base.top + viewport.scrollTop, height: r.height};
            }
        }
        hoverRect = best;
    }

    function armOnArrow(event: KeyboardEvent) {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        armed = true;
        // Keyboard takes over; a stale pointer highlight would leave two rows
        // looking live at once.
        hoverRect = null;
    }

    function handleKeydown(event: KeyboardEvent) {
        if (!shortcut) return;
        if (event.key !== 'k' || !(event.metaKey || event.ctrlKey)) return;
        // Beat the browser's own ⌘K (address-bar search / Slack-style handlers).
        event.preventDefault();
        open = !open;
    }

    function choose(value: string) {
        open = false;
        onSelect?.(value);
    }

    // Land on the command root rather than the content wrapper, so arrow keys
    // drive the list the moment the palette opens.
    function focusRoot(event: Event) {
        event.preventDefault();
        rootEl?.focus();
    }

    const fullContentProps = $derived(
        mergeProps(
            {
                side: 'bottom' as const,
                align: 'start' as const,
                sideOffset: 6,
                class: 'command-palette',
                onOpenAutoFocus: focusRoot
            },
            contentProps
        )
    );

    // The sheet has no bits-ui trigger, so the same props are built by hand.
    const sheetTriggerProps = $derived({
        'aria-haspopup': 'dialog',
        'aria-expanded': open,
        'data-state': open ? 'open' : 'closed',
        onclick: () => (open = true)
    });
</script>

<svelte:window onkeydown={handleKeydown} />

{#snippet results()}
    <CommandPrimitive.Root
        label={paletteLabel}
        loop
        shouldFilter={false}
        bind:ref={rootEl}
        class={armed ? 'command-root armed' : 'command-root'}
        onkeydown={armOnArrow}
    >
        <CommandResults
            {groups}
            {current}
            onSelect={choose}
            bind:viewport={viewportEl}
            onpointermove={trackPointer}
            onpointerleave={() => (hoverRect = null)}
        >
            {#if hoverRect && !asSheet}
                <span
                    class="command-hover-bg"
                    style:top="{hoverRect.top}px"
                    style:height="{hoverRect.height}px"
                ></span>
            {/if}
        </CommandResults>
    </CommandPrimitive.Root>
{/snippet}

{#if asSheet}
    {@render trigger({props: sheetTriggerProps, open, shortcutKeys})}
    <BottomSheet
        bind:open
        title={paletteLabel}
        contentProps={{class: 'command-sheet', onOpenAutoFocus: focusRoot}}
    >
        {@render results()}
    </BottomSheet>
{:else}
    <PopoverPrimitive.Root bind:open>
        <PopoverPrimitive.Trigger>
            {#snippet child({props})}
                {@render trigger({props, open, shortcutKeys})}
            {/snippet}
        </PopoverPrimitive.Trigger>

        <PopoverPrimitive.Portal>
            <PopoverPrimitive.Content {...fullContentProps as PopoverContentProps}>
                {@render results()}
            </PopoverPrimitive.Content>
        </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
{/if}

<style>
    /* Match the switcher trigger's spacing between the icon, label and indicator. */
    :global(.command-palette .command-list .command-item),
    :global(.command-sheet .command-list .command-item) {
        gap: var(--space-2);
    }

    :global(.command-palette .command-list .item-check) {
        padding-inline: 0;
    }

    /* Touch-sized rows, like the other menus' sheets, with the same padding on
       both sides. The check drops its own padding so it doesn't add to the right. */
    :global(.command-sheet .command-list .command-item) {
        min-height: 2.75rem;
        padding-inline: var(--space-3);
    }

    :global(.command-sheet .command-list .item-check) {
        padding-inline: 0;
    }

    /* The content is portalled out of this subtree, so everything below is
       addressed globally under the palette's own class. */
    :global(.command-palette) {
        z-index: var(--layer-overlay);
        display: flex;
        flex-direction: column;
        /* Concentric with the rows, like every menu around sm rows. */
        border-radius: var(--corner-md);
        border: var(--border);
        background-color: var(--color-surface-raised);
        box-shadow: var(--elevation-1);
        padding: var(--space-1);
        overflow: hidden;

        &[data-state='open'] {
            animation: command-in 120ms var(--easing-default, ease);
        }

        &[data-state='closed'] {
            animation: command-out 100ms var(--easing-default, ease);
        }
    }

    :global(.command-palette .command-root) {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    /* ── List ─────────────────────────────────────────────────────────── */

    /* Only the size is decided here; rows and headings come styled from
       `CommandResults`. */
    :global(.command-palette .command-list) {
        max-height: min(22rem, calc(var(--bits-floating-available-height, 999px) - 2rem));
    }

    /* Single highlight that follows the row nearest the pointer — the palette's
       echo of the sidebar's proximity hover. */
    :global(.command-palette .command-hover-bg) {
        position: absolute;
        left: 0;
        right: 0;
        /* Highlight paints below the rows, which sit at --command-item-z. */
        --command-hover-bg-z: 0;
        z-index: var(--command-hover-bg-z);
        pointer-events: none;
        background: var(--color-hover);
        border-radius: var(--corner-sm);
        transition:
            top 200ms var(--easing-spring),
            height 200ms var(--easing-spring);
    }

    /* The keyboard wash is only painted once the user has arrowed into the
       list (see `armed`); until then the pointer highlight above is the only
       hover cue. */
    :global(.command-palette .command-root.armed .command-item[data-selected]) {
        background-color: var(--color-hover);
    }

    @keyframes command-in {
        from {
            opacity: 0;
            scale: 0.97;
        }
        to {
            opacity: 1;
            scale: 1;
        }
    }

    @keyframes command-out {
        from {
            opacity: 1;
            scale: 1;
        }
        to {
            opacity: 0;
            scale: 0.97;
        }
    }
</style>
