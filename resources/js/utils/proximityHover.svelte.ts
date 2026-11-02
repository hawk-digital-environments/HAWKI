export interface ItemRect {
    top: number;
    left: number;
    width: number;
    height: number;
}

interface Options {
    axis?: 'x' | 'y';
}

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
 * a sidebar resize).
 */
export function createProximityHover(options: Options = {}) {
    const { axis = 'y' } = options;

    let container: HTMLElement | null = null;
    const items = new Map<number, HTMLElement | null>();
    let rects = $state<Record<number, ItemRect>>({});
    let activeIndex = $state<number | null>(null);
    let selectedIndex = $state<number | null>(null);
    let rafId: number | null = null;

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
            next[idx] = {
                top: (r.top - containerRect.top) / scale + scrollTop,
                left: (r.left - containerRect.left) / scale + scrollLeft,
                width: r.width / scale,
                height: r.height / scale,
            };
        }
        rects = next;
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
        });
    }

    function onmouseenter() {
        measureItems();
    }

    function onmouseleave() {
        if (rafId !== null) { cancelAnimationFrame(rafId); rafId = null; }
        activeIndex = null;
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
            for (const el of items.values()) {
                if (!el) continue;
                const r = el.getBoundingClientRect();
                if (axis === 'y' ? r.height === 0 : r.width === 0) continue;
                const [cursor, start, end] = axis === 'y'
                    ? [event.clientY, r.top, r.bottom]
                    : [event.clientX, r.left, r.right];
                if (cursor >= start - slack && cursor <= end + slack) return true;
            }
            return false;
        },
        /** Mark which item is selected and re-measure so its highlight tracks it. */
        setSelected(idx: number | null) {
            selectedIndex = idx;
            measureItems();
        },
        measureItems,
        handlers: { onmousemove, onmouseenter, onmouseleave },
    };
}
