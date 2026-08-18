<!--
  @component Scrollable list container with proximity hover. Owns the two
  sliding highlights that sit behind the rows — one that follows the current
  selection, one that follows the row nearest the pointer — and the measuring
  needed to keep them there. ListItem children register themselves through the
  context exported here so the container can track them.

  Rows are laid out as a vertical flex column; the highlight of a row marked
  `inset` is pulled in from the left by `--list-inset`, so nested rows can nest
  their highlight too.
-->
<script module lang="ts">
    import {createContext} from 'svelte';

    /** Per-row presentation flags the container needs to position highlights. */
    export interface ListItemState {
        /** Whether the row is the current selection. */
        active: boolean;
        /** Whether the row's highlight is indented (nested rows). */
        inset: boolean;
    }

    /** Registration API exposed to descendant ListItem rows. */
    export interface ListContextValue {
        /** Register a row's element; returns its stable index. */
        register: (el: HTMLElement) => number;
        /** Release a previously registered index. */
        unregister: (index: number) => void;
        /** Report a row's current active/inset state. */
        setState: (index: number, state: ListItemState) => void;
    }

    const [getListContext, setListContext] = createContext<ListContextValue>();

    /** The enclosing list, or null for rows used standalone (e.g. in a footer). */
    export function useList(): ListContextValue | null {
        try {
            return getListContext() ?? null;
        } catch {
            return null;
        }
    }
</script>

<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {createProximityHover} from '$lib/utils/proximityHover.svelte.js';
    import { fade } from 'svelte/transition';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The rows (ListItem instances, or anything wrapping them). */
        children: Snippet;
        /** Suppress both sliding highlights (active selection + proximity hover),
            e.g. while the list is sliding through a transition. */
        paused?: boolean;
    }

    const {children, paused = false, class: className, ...rest}: Props = $props();

    const hover = createProximityHover({axis: 'y'});
    // Clear any lingering hover when suppression turns on, so nothing snaps back
    // into place when it turns off again. On release, re-measure so the active
    // highlight fades back in at the settled row positions rather than the stale
    // mid-transition coordinates captured while the rows were moving.
    $effect(() => {
        if (paused) hover.handlers.onmouseleave();
        else hover.measureItems();
    });
    let listEl = $state<HTMLElement | null>(null);
    $effect(() => hover.setContainer(listEl));

    // Rows register in mount order; indices are handed out monotonically and
    // freed on unmount. Highlights are positioned from measured rects, so the
    // exact index values never need to stay contiguous.
    let nextIndex = 0;
    let itemStates = $state<Record<number, ListItemState>>({});
    // The registered row elements, kept alongside the hover's own map so the
    // container can hit-test rows without reaching for a class selector.
    const itemElements = new Map<number, HTMLElement>();

    setListContext({
        register(el) {
            const index = nextIndex++;
            itemElements.set(index, el);
            hover.registerItem(index, el);
            return index;
        },
        unregister(index) {
            itemElements.delete(index);
            hover.registerItem(index, null);
            delete itemStates[index];
        },
        setState(index, state) {
            itemStates[index] = state;
        }
    });

    /** Index of the row flagged active, if any. */
    const activeIndex = $derived.by(() => {
        for (const [index, state] of Object.entries(itemStates)) {
            if (state.active) return Number(index);
        }
        return null;
    });

    // Keep the sliding active highlight on the selected row; depends on
    // `itemStates` so it re-measures when rows are added or removed.
    $effect(() => {
        itemStates;
        hover.setSelected(activeIndex);
    });

    // While the list is reflowing (breakpoint change, container width animation)
    // the highlights snap to the new row positions instead of sliding across
    // from their now-stale coordinates.
    let reflowing = $state(false);
    let settleTimer: ReturnType<typeof setTimeout> | null = null;

    // The active highlight should glide when the *selection itself* moves (e.g.
    // child → parent as a rail collapses) even though that coincides with a
    // reflow. Track index changes so the snap-guard applies only to spurious
    // re-measures of the same row, never to a genuine selection move mid-flight.
    // svelte-ignore state_referenced_locally -- intentional one-time initial value
    let prevActiveIndex = activeIndex;
    let selectionMoved = $state(false);

    $effect(() => {
        if (activeIndex !== prevActiveIndex) {
            prevActiveIndex = activeIndex;
            selectionMoved = true;
        }
    });

    function reflow() {
        reflowing = true;
        hover.measureItems();
        if (settleTimer) clearTimeout(settleTimer);
        // Re-enable sliding once the viewport stops changing; clear the
        // selection-move flag so the next reflow snaps as usual.
        settleTimer = setTimeout(() => {
            reflowing = false;
            selectionMoved = false;
        }, 120);
    }

    $effect(() => {
        const el = listEl;
        if (!el) return;
        // The observer fires for the container's own width animation and the
        // initial measure; `resize` covers reflows that change row heights
        // without changing the container box (e.g. a mobile font-size bump).
        const observer = new ResizeObserver(() => reflow());
        observer.observe(el);
        // Rows can also be reordered or swapped without any box changing (a
        // keyed list moving the current row to the top), which neither observer
        // above notices — the highlights would keep sitting on the row that
        // used to be there.
        const mutations = new MutationObserver(() => reflow());
        mutations.observe(el, {childList: true, subtree: true});
        window.addEventListener('resize', reflow);
        return () => {
            observer.disconnect();
            mutations.disconnect();
            window.removeEventListener('resize', reflow);
            if (settleTimer) clearTimeout(settleTimer);
        };
    });

    // Regions inside the container that are not rows (e.g. a section picker or
    // search trigger) opt out with `data-list-no-hover`: the proximity highlight
    // would otherwise reach for the nearest row while the pointer is somewhere
    // that has nothing to do with it.
    function isExcluded(event: MouseEvent) {
        const target = event.target;
        return target instanceof Element && !!target.closest('[data-list-no-hover]');
    }

    /** Half the row gap — the slack that keeps the gaps between rows "on-row". */
    const GAP_SLACK = 4;

    /** Whether the pointer is over a row, or in a gap directly between two. */
    function nearRow(event: MouseEvent) {
        for (const row of itemElements.values()) {
            const r = row.getBoundingClientRect();
            if (r.height === 0) continue;
            if (event.clientY >= r.top - GAP_SLACK && event.clientY <= r.bottom + GAP_SLACK) {
                return true;
            }
        }
        return false;
    }

    function handleMousemove(event: MouseEvent) {
        // The highlight belongs to a row the pointer is actually on, so it
        // disappears in the empty space around the list instead of reaching for
        // the nearest row. The narrow gaps *between* rows still count as "on a
        // row" — otherwise the highlight would blink off every time the pointer
        // crossed one.
        if (!nearRow(event) || isExcluded(event)) {
            hover.handlers.onmouseleave();
            return;
        }
        hover.handlers.onmousemove(event);
    }

    function handleMouseenter(event: MouseEvent) {
        if (isExcluded(event)) return;
        hover.handlers.onmouseenter();
    }

    const activeRect = $derived(hover.selectedRect);
    const hoverRect = $derived(
        hover.activeIndex !== null ? hover.itemRects[hover.activeIndex] : null
    );
    const hoverOnActive = $derived(hover.activeIndex !== null && hover.activeIndex === activeIndex);
    const activeInset = $derived(activeIndex !== null && (itemStates[activeIndex]?.inset ?? false));
    const hoverInset = $derived(
        hover.activeIndex !== null && (itemStates[hover.activeIndex]?.inset ?? false)
    );
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -- the mouse handlers
     only drive the decorative proximity highlight; rows stay fully operable
     without them -->
<div
    {...rest}
    class={['list', className]}
    bind:this={listEl}
    onmousemove={paused ? undefined : handleMousemove}
    onmouseenter={paused ? undefined : handleMouseenter}
    onmouseleave={hover.handlers.onmouseleave}
>
    {#if activeRect}
        <span
            class="active-bg"
            class:inset={activeInset}
            class:hidden={paused}
            class:no-transition={paused || (reflowing && !selectionMoved)}
            style:top="{activeRect.top}px"
            style:height="{activeRect.height}px"
        ></span>
    {/if}
    {#if hoverRect && !paused}
        <span
            class="hover-bg"
            class:on-active={hoverOnActive}
            class:inset={hoverInset}
            class:no-transition={reflowing}
            transition:fade={{ duration: 50 }}
            style:top="{hoverRect.top}px"
            style:height="{hoverRect.height}px"
        ></span>
    {/if}
    {@render children()}
</div>

<style>
    .list {
        position: relative;
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        overflow-y: auto;
        /* Rows may travel on the x-axis during a transition (e.g. a drill-down
           slide); clip that overflow so no horizontal scrollbar flashes
           mid-slide (overflow-y:auto would otherwise resolve overflow-x to auto
           too). */
        overflow-x: hidden;
    }

    /* Sliding highlight that follows the active selection. */
    .active-bg {
        position: absolute;
        left: 0;
        right: 0;
        z-index: 0;
        pointer-events: none;
        background: var(--color-active-surface);
        border-radius: var(--corner-sm);
        opacity: 1;
        transition:
            top 200ms cubic-bezier(0.34, 1.12, 0.64, 1),
            height 200ms cubic-bezier(0.34, 1.12, 0.64, 1),
            left 200ms cubic-bezier(0.34, 1.12, 0.64, 1),
            background 150ms ease,
            opacity 220ms ease-in-out;
    }

    /* Crossfade the active highlight through a transition rather than letting it
       chase the moving rows. Its position stays pinned while hidden (see
       `no-transition`), so it fades out in place and fades back in already
       sitting on the new active row. */
    .active-bg.hidden {
        opacity: 0;
    }

    /* Single sliding highlight that follows the nearest row. */
    .hover-bg {
        position: absolute;
        left: 0;
        right: 0;
        z-index: 0;
        pointer-events: none;
        background: var(--color-hover);
        border-radius: var(--corner-sm);
        transition:
            top 200ms var(--easing-spring),
            height 200ms var(--easing-spring),
            left 200ms var(--easing-spring);
    }

    /* Hovering the active row darkens its highlight: the active surface is
       translucent in both themes, so a second layer deepens the row. */
    .hover-bg.on-active {
        background: var(--color-active-surface);
    }

    /* During a reflow, snap straight to the re-measured position instead of
       gliding across from stale coordinates. The active highlight keeps its
       opacity transition so it can still crossfade through a transition while
       its position stays pinned. */
    .active-bg.no-transition {
        transition: opacity 220ms ease-in-out;
    }

    .hover-bg.no-transition {
        transition: none;
    }

    /* Nested rows: inset the highlight so it sits under the parent's label. The
       amount is the list's to set — rows only say *that* they are nested. */
    .active-bg.inset,
    .hover-bg.inset {
        left: var(--list-inset, var(--space-4));
    }
</style>
