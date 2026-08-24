<script module lang="ts">
    import type { Component } from 'svelte';

    /** One row of the palette. */
    export interface CommandItemDefinition {
        /** Stable unique id. Returned by `onSelect`. */
        value: string;
        /** Text shown in the row. */
        label: string;
        /** Leading icon. */
        icon?: any;
        /** Groups rows under a heading. Groups keep the order they first appear in. */
        groupLabel?: string;
        /** Non-selectable row. */
        disabled?: boolean;
    }
</script>

<!--
  @component Command palette anchored to its own trigger. Arrow keys / Enter
  drive the selection, and an optional ⌘K (Ctrl+K) shortcut opens it from
  anywhere.

  It is a Popover rather than a centred modal so it can double as an in-place
  dropdown: the trigger keeps its `data-state` while open, so callers can style
  it as one connected control.
-->
<script lang="ts">
    import {
        Command as CommandPrimitive,
        Popover as PopoverPrimitive,
        mergeProps,
        type PopoverContentProps
    } from 'bits-ui';
    import type {Snippet} from 'svelte';
    import TickIcon from '$lib/components/ui/icons/iconset/Tick02Icon.svelte';

    interface Props {
        /** Rows to show, in display order. */
        items: CommandItemDefinition[];
        /** Whether the palette is open. Supports bind:open. */
        open?: boolean;
        /** Value marked as the current one — renders a trailing check. */
        current?: string;
        /** Fired with the chosen row's `value`. The palette closes itself first. */
        onSelect?: (value: string) => void;
        /** Screen-reader name for the palette. */
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
        label = 'Befehle',
        shortcut = true,
        trigger,
        contentProps
    }: Props = $props();

    /** Rows bucketed under their heading, in first-seen order. */
    const groups = $derived.by(() => {
        const buckets = new Map<string, CommandItemDefinition[]>();
        for (const item of items) {
            const key = item.groupLabel ?? '';
            const bucket = buckets.get(key);
            if (bucket) bucket.push(item);
            else buckets.set(key, [item]);
        }
        return Array.from(buckets, ([groupLabel, groupItems]) => ({groupLabel, items: groupItems}));
    });

    // ⌘ on Apple platforms, Ctrl elsewhere. Resolved after mount so the server
    // render stays platform-neutral.
    let isApple = $state(false);
    $effect(() => {
        isApple = /mac|iphone|ipad|ipod/i.test(navigator.userAgent);
    });

    const shortcutKeys = $derived(isApple ? ['⌘', 'K'] : ['Strg', 'K']);

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

    const fullContentProps = $derived(
        mergeProps(
            {
                side: 'bottom' as const,
                align: 'start' as const,
                sideOffset: 6,
                class: 'command-palette',
                // Land on the command root rather than the content wrapper, so
                // arrow keys drive the list the moment the palette opens.
                onOpenAutoFocus: (event: Event) => {
                    event.preventDefault();
                    rootEl?.focus();
                }
            },
            contentProps
        )
    );
</script>

<svelte:window onkeydown={handleKeydown} />

<PopoverPrimitive.Root bind:open>
    <PopoverPrimitive.Trigger>
        {#snippet child({props})}
            {@render trigger({props, open, shortcutKeys})}
        {/snippet}
    </PopoverPrimitive.Trigger>

    <PopoverPrimitive.Portal>
        <PopoverPrimitive.Content {...fullContentProps as PopoverContentProps}>
            <CommandPrimitive.Root
                {label}
                loop
                shouldFilter={false}
                bind:ref={rootEl}
                class={armed ? 'command-root armed' : 'command-root'}
                onkeydown={armOnArrow}
            >
                <CommandPrimitive.List
                    class="command-list"
                    onpointermove={trackPointer}
                    onpointerleave={() => (hoverRect = null)}
                >
                    <CommandPrimitive.Viewport class="command-viewport" bind:ref={viewportEl}>
                        {#if hoverRect}
                            <span
                                class="command-hover-bg"
                                style:top="{hoverRect.top}px"
                                style:height="{hoverRect.height}px"
                            ></span>
                        {/if}
                        {#each groups as group (group.groupLabel)}
                            <CommandPrimitive.Group class="command-group" value={group.groupLabel}>
                                {#if group.groupLabel}
                                    <CommandPrimitive.GroupHeading class="command-group-heading">
                                        {group.groupLabel}
                                    </CommandPrimitive.GroupHeading>
                                {/if}
                                <CommandPrimitive.GroupItems class="command-group-items">
                                    {#each group.items as item (item.value)}
                                        {@const Icon = item.icon}
                                        <CommandPrimitive.Item
                                            class="command-item"
                                            value={item.value}
                                            disabled={item.disabled}
                                            onSelect={() => choose(item.value)}
                                        >
                                            <span class="item-icon" aria-hidden="true">
                                                {#if Icon}
                                                    <Icon size={17} strokeWidth={2} />
                                                {/if}
                                            </span>
                                            <span class="item-label">{item.label}</span>
                                            <span
                                                class="item-check"
                                                class:on={current === item.value}
                                                aria-hidden="true"
                                            >
                                                <TickIcon size={15} strokeWidth={2.5} />
                                            </span>
                                        </CommandPrimitive.Item>
                                    {/each}
                                </CommandPrimitive.GroupItems>
                            </CommandPrimitive.Group>
                        {/each}
                    </CommandPrimitive.Viewport>
                </CommandPrimitive.List>

            </CommandPrimitive.Root>
        </PopoverPrimitive.Content>
    </PopoverPrimitive.Portal>
</PopoverPrimitive.Root>

<style>
    /* The content is portalled out of this subtree, so everything below is
       addressed globally under the palette's own class. */
    :global(.command-palette) {
        z-index: var(--layer-overlay);
        display: flex;
        flex-direction: column;
        min-width: 15rem;
        /* Concentric with the rows: item radius + the container's own padding. */
        border-radius: calc(var(--corner-sm) + var(--space-1));
        border: var(--border);
        background-color: var(--color-surface-raised);
        box-shadow: var(--elevation-2);
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

    :global(.command-palette .command-list) {
        min-height: 0;
        max-height: min(22rem, calc(var(--bits-floating-available-height, 999px) - 2rem));
        overflow-y: auto;
        /* The list still scrolls; the bar itself is hidden so a long result set
           doesn't put a gutter between the rows and the panel edge. Keyboard
           navigation scrolls the active row into view, so nothing depends on
           the bar being visible. */
        overscroll-behavior: contain;
        scrollbar-width: none;
        -ms-overflow-style: none;
        -webkit-overflow-scrolling: touch;
    }

    :global(.command-palette .command-list::-webkit-scrollbar) {
        display: none;
    }

    /* Rows sit apart on the same rhythm as the sidebar's nav list. The gap is
       set at every level between the viewport and the rows, since Command wraps
       them in group / group-items containers. */
    :global(.command-palette .command-viewport),
    :global(.command-palette .command-group),
    :global(.command-palette .command-group-items) {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }

    :global(.command-palette .command-group-heading) {
        padding: var(--space-2) var(--space-2_5) var(--space-1);
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium, 500);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }

    /* Headings carry their own top padding, so the first one would double up
       against the container's own padding. */
    :global(.command-palette .command-group:first-of-type .command-group-heading) {
        padding-top: var(--space-1);
    }

    /* Anchors the sliding hover highlight. */
    :global(.command-palette .command-viewport) {
        position: relative;
    }

    /* Single highlight that follows the row nearest the pointer — the palette's
       echo of the sidebar's proximity hover. */
    :global(.command-palette .command-hover-bg) {
        position: absolute;
        left: 0;
        right: 0;
        z-index: 0;
        pointer-events: none;
        background: var(--color-hover);
        border-radius: var(--corner-sm);
        transition:
            top 200ms var(--easing-spring),
            height 200ms var(--easing-spring);
    }

    :global(.command-palette .command-item) {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        min-height: 2.25rem;
        padding: 0 var(--space-2) 0 var(--space-2_5);
        border-radius: var(--corner-sm);
        font-size: var(--font-size-xs);
        color: var(--color-text);
        cursor: pointer;
        outline: none;
        transition: background-color var(--transition-fast);
    }

    /* In Command, `data-selected` marks the row the keyboard/pointer is on —
       the neutral wash. The accent ink + check mark below mark the row that is
       actually the current one. */
    :global(.command-palette .command-root.armed .command-item[data-selected]) {
        background-color: var(--color-hover);
    }

    :global(.command-palette .command-item[data-disabled]) {
        color: var(--color-text-disabled);
        cursor: not-allowed;
    }

    :global(.command-palette .item-icon) {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    :global(.command-palette .item-label) {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* The check keeps its slot at all times so labels never shift as the
       selection moves between rows. */
    :global(.command-palette .item-check) {
        display: inline-flex;
        flex-shrink: 0;
        padding-inline: var(--space-1);
        color: var(--color-active-text);
        opacity: 0;
        transform: scale(0.7);
        transition:
            opacity 140ms var(--easing-default),
            transform 200ms var(--easing-spring);
    }

    :global(.command-palette .item-check.on) {
        opacity: 1;
        transform: scale(1);
    }

    /* The current row wears the sidebar's active highlight — same surface and
       ink as the nav's sliding highlight — whether or not it is also the row
       the keyboard/pointer is on. */
    :global(.command-palette .command-item:has(.item-check.on)) {
        background-color: var(--color-active-surface);
        color: var(--color-active-text);
    }

    :global(.command-palette .command-item:has(.item-check.on) .item-icon) {
        color: inherit;
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

    /* Roomier rows on touch. */
    @media (--bp-md-and-smaller) {
        :global(.command-palette .command-item) {
            min-height: 2.75rem;
            font-size: var(--font-size-sm);
        }
    }
</style>
