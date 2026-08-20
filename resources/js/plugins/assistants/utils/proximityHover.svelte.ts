export interface ItemRect {
	top: number;
	left: number;
	width: number;
	height: number;
}

interface Options {
	axis?: 'x' | 'y';
}

export function createProximityHover(options: Options = {}) {
	const { axis = 'y' } = options;

	let container: HTMLElement | null = null;
	const items = new Map<number, HTMLElement | null>();
	let rects = $state<Record<number, ItemRect>>({});
	let activeIndex = $state<number | null>(null);
	let selectedIndex = $state<number | null>(null);
	let rafId: number | null = null;

	function measureItems() {
		if (!container) return;
		const containerRect = container.getBoundingClientRect();
		const next: Record<number, ItemRect> = {};
		for (const [idx, el] of items) {
			if (!el) continue;
			const r = el.getBoundingClientRect();
			next[idx] = {
				top: r.top - containerRect.top,
				left: r.left - containerRect.left,
				width: r.width,
				height: r.height,
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
			const cx = event.clientX - containerRect.left;
			const cy = event.clientY - containerRect.top;
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
		/** Mark which item is selected and re-measure so its highlight tracks it. */
		setSelected(idx: number | null) {
			selectedIndex = idx;
			measureItems();
		},
		measureItems,
		handlers: { onmousemove, onmouseenter, onmouseleave },
	};
}
