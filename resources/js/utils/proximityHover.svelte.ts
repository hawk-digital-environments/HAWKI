import {untrack} from 'svelte';
import {Spring} from 'svelte/motion';
import {springs} from '$lib/utils/springs.js';
import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte.js';

export interface ItemRect {
    top: number;
    left: number;
    width: number;
    height: number;
}

interface Options {
    axis?: 'x' | 'y';
    /**
     * Where the hover highlight comes from when the pointer arrives on an item.
     * `'item'` (default) shows it right on that item; `'selection'` slides it out
     * of the selected item, and back into it when the pointer leaves, so the two
     * highlights read as one surface.
     */
    hoverFrom?: 'item' | 'selection';
    /**
     * Reshapes an item's measured rect before the highlights use it, e.g. to pull a
     * nested row's highlight in from the left. Whatever it returns rides the same
     * springs, so such a change glides along with the move instead of jumping.
     */
    adjustRect?: (idx: number, rect: ItemRect) => ItemRect;
}

const EMPTY_RECT: ItemRect = {top: 0, left: 0, width: 0, height: 0};

/**
 * Tracks the registered item nearest to the pointer and measures every item
 * relative to one scrollable container. Consumers use the returned rectangles
 * to position a single animated hover/selection surface instead of giving each
 * row an independent background.
 *
 * Call `setContainer()` with the positioning parent, register rows with stable
 * numeric ids (or hand over a list that changes as a whole with `setItems()`),
 * and forward the three `handlers` to the container. Re-run `measureItems()`
 * after layout changes that do not originate from pointer movement (for example
 * a sidebar resize), or `snapToItems()` when the highlights should jump there
 * instead of gliding.
 *
 * `selectionHighlight` and `hoverHighlight` are the two highlights already in
 * motion, on springs: render straight from them and add no position transitions
 * of your own.
 */
export function createProximityHover(options: Options = {}) {
    const { axis = 'y', hoverFrom = 'item', adjustRect } = options;
    const reducedMotion = useReducedMotion();

    let container: HTMLElement | null = null;
    const items = new Map<number, HTMLElement | null>();
    let rects = $state<Record<number, ItemRect>>({});
    let activeIndex = $state<number | null>(null);
    let selectedIndex = $state<number | null>(null);
    let rafId: number | null = null;

    // The selection is the bigger move, so it takes the slower tier.
    const selectionSpring = new Spring<ItemRect>(EMPTY_RECT, springs.moderate);
    const hoverSpring = new Spring<ItemRect>(EMPTY_RECT, springs.fast);
    let selectionPlaced = false;
    let hoverPlaced = $state(false);
    // Whether the pointer was on an item other than the selected one at the last sync.
    let hoverShown = false;

    /**
     * Points both springs at the current rects. Runs from pointer handlers and from
     * consumers' effects alike, so it reads untracked: it must never make an effect
     * depend on the rects it is about to replace.
     */
    function syncHighlights(snap = false) {
        untrack(() => {
            const instant = snap || reducedMotion.current;
            const selected = selectedIndex !== null ? rects[selectedIndex] ?? null : null;
            if (selected) {
                // The first placement lands in place rather than flying in from the corner.
                selectionSpring.set(selected, {instant: instant || !selectionPlaced});
                selectionPlaced = true;
            } else {
                selectionPlaced = false;
            }

            const hovered = activeIndex !== null && activeIndex !== selectedIndex
                ? rects[activeIndex] ?? null
                : null;
            if (hovered) {
                if (!hoverShown) {
                    // Arriving, so it starts fresh instead of from wherever it last faded out.
                    hoverSpring.set(hoverFrom === 'selection' && selected ? selected : hovered, {instant: true});
                }
                hoverSpring.stiffness = springs.fast.stiffness;
                hoverSpring.damping = springs.fast.damping;
                hoverSpring.set(hovered, {instant});
                hoverPlaced = true;
            } else if (activeIndex === null && hoverShown && hoverFrom === 'selection' && selected) {
                // Leaving: it sinks back into the selection while it fades, at the
                // selection's own pace.
                hoverSpring.stiffness = springs.moderate.stiffness;
                hoverSpring.damping = springs.moderate.damping;
                hoverSpring.set(selected, {instant});
            }
            hoverShown = hovered !== null;
        });
    }

    /**
     * How much a transform on the way up (a popup's opening scale) currently
     * magnifies the container. Anything within `offsetWidth`'s rounding is no
     * transform at all, and must not skew long distances by that rounding.
     */
    function scaleOf(el: HTMLElement, width: number) {
        return el.offsetWidth === 0 || Math.abs(width - el.offsetWidth) <= 0.5
            ? 1
            : width / el.offsetWidth;
    }

    function measureItems() {
        if (!container) return;
        const containerRect = container.getBoundingClientRect();
        const scale = scaleOf(container, containerRect.width);
        // Relative to the container's *content* origin rather than its visible
        // box — that is what an absolutely positioned highlight is placed
        // against, so a scrolled container keeps its highlights on the items
        // instead of dropping them by the scroll offset.
        const {scrollTop, scrollLeft} = container;
        const next: Record<number, ItemRect> = {};
        for (const [idx, el] of items) {
            if (!el) continue;
            const r = el.getBoundingClientRect();
            const rect = {
                top: (r.top - containerRect.top) / scale + scrollTop,
                left: (r.left - containerRect.left) / scale + scrollLeft,
                width: r.width / scale,
                height: r.height / scale,
            };
            next[idx] = adjustRect ? adjustRect(idx, rect) : rect;
        }
        rects = next;
        syncHighlights();
    }

    function findClosest(cx: number, cy: number): number | null {
        let best: number | null = null;
        let bestDist = Infinity;
        for (const [idx, rect] of Object.entries(rects)) {
            const r = rect as ItemRect;
            const center = axis === 'y' ? r.top + r.height / 2 : r.left + r.width / 2;
            const cursor = axis === 'y' ? cy : cx;
            // inside the item
            const inMain = axis === 'y'
                ? cy >= r.top && cy <= r.top + r.height
                : cx >= r.left && cx <= r.left + r.width;
            const dist = inMain ? 0 : Math.abs(cursor - center);
            if (dist < bestDist) {
                bestDist = dist;
                best = Number(idx);
            }
        }
        return best;
    }

    function onmousemove(event: MouseEvent) {
        if (!container) return;
        if (rafId !== null) cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(() => {
            rafId = null;
            const containerRect = container!.getBoundingClientRect();
            const scale = scaleOf(container!, containerRect.width);
            // Same frame of reference as the measured rects (see measureItems).
            const cx = (event.clientX - containerRect.left) / scale + container!.scrollLeft;
            const cy = (event.clientY - containerRect.top) / scale + container!.scrollTop;
            measureItems();
            activeIndex = findClosest(cx, cy);
            syncHighlights();
        });
    }

    function onmouseenter() {
        measureItems();
    }

    function onmouseleave() {
        if (rafId !== null) { cancelAnimationFrame(rafId); rafId = null; }
        activeIndex = null;
        syncHighlights();
    }

    /** Live main-axis spans of the visible items; inert (collapsed) items don't count. */
    function hitSpans(): [number, number][] {
        const spans: [number, number][] = [];
        for (const el of items.values()) {
            if (!el || el.closest('[inert]')) continue;
            const r = el.getBoundingClientRect();
            if (axis === 'y' ? r.height === 0 : r.width === 0) continue;
            spans.push(axis === 'y' ? [r.top, r.bottom] : [r.left, r.right]);
        }
        return spans;
    }

    return {
        get activeIndex() { return activeIndex; },
        get itemRects() { return rects; },
        /** Index of the current selection, whose highlight slides into place. */
        get selectedIndex() { return selectedIndex; },
        /** Rect of the selected item, or null when nothing is selected. */
        get selectedRect() {
            return selectedIndex !== null ? rects[selectedIndex] ?? null : null;
        },
        /** The selection highlight's rect in motion, or null while nothing is selected. */
        get selectionHighlight(): ItemRect | null {
            return selectedIndex !== null && rects[selectedIndex] ? selectionSpring.current : null;
        },
        /**
         * The hover highlight's rect in motion, or null before the first hover. It
         * stays set once the pointer leaves, so the highlight can fade out where it
         * lands; `hoverVisible` says whether to show it.
         */
        get hoverHighlight(): ItemRect | null {
            return hoverPlaced ? hoverSpring.current : null;
        },
        /** Whether the pointer is on an item other than the selected one. */
        get hoverVisible() {
            return activeIndex !== null && activeIndex !== selectedIndex;
        },
        setContainer(el: HTMLElement | null) { container = el; },
        registerItem(idx: number, el: HTMLElement | null) { items.set(idx, el); },
        /** Replace every registered item; each one's id is its position in `elements`. */
        setItems(elements: readonly HTMLElement[]) {
            items.clear();
            elements.forEach((el, idx) => items.set(idx, el));
        },
        /**
         * Whether the pointer is over an item, or within `slack` px of one on
         * the main axis — the slack keeps the gaps between items "on-item".
         */
        isNearItem(event: MouseEvent, slack = 4) {
            const cursor = axis === 'y' ? event.clientY : event.clientX;
            return hitSpans().some(([start, end]) => cursor >= start - slack && cursor <= end + slack);
        },
        /**
         * Whether the pointer is anywhere between the first and the last item on
         * the main axis (plus `slack`), e.g. over a section label between rows.
         */
        isWithinItems(event: MouseEvent, slack = 4) {
            const spans = hitSpans();
            if (spans.length === 0) return false;
            const cursor = axis === 'y' ? event.clientY : event.clientX;
            const start = Math.min(...spans.map(([s]) => s));
            const end = Math.max(...spans.map(([, e]) => e));
            return cursor >= start - slack && cursor <= end + slack;
        },
        /** Mark which item is selected and re-measure so its highlight tracks it. */
        setSelected(idx: number | null) {
            selectedIndex = idx;
            measureItems();
        },
        measureItems,
        /** Re-measure and put both highlights straight on their items, e.g. after a resize. */
        snapToItems() {
            measureItems();
            syncHighlights(true);
        },
        handlers: { onmousemove, onmouseenter, onmouseleave },
    };
}
