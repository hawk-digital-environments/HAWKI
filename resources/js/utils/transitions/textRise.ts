/**
 * Svelte CSS transition for stacked hero copy (icon, headline, supporting line): each line
 * rises a few pixels out of a soft blur, and a per-line `delay` staggers them so the eye
 * lands on the headline first. Leaving is decoupled from the entrance — a quick, quiet fade
 * in place with no blur and no drop — so swapping one block of copy for another doesn't
 * read as the old one replaying its reveal in reverse.
 *
 * Follows the "texts reveal" pattern from transitions.dev (500ms, 12px, 3px blur, 40ms
 * stagger on a smooth ease-out; 150ms fade out). Durations collapse to zero when the user
 * prefers reduced motion.
 *
 * @param node - The element being transitioned (provided by Svelte).
 * @param params.direction - `'in'` (enter, default) or `'out'` (leave).
 * @param params.index - The line's position in the stack; delays it by `index × 40ms`.
 *
 * @example
 * <h1 in:textRise={{index: 1}} out:textRise={{direction: 'out'}}>…</h1>
 */
import {motionDuration} from '$lib/utils/transitions/reducedMotion.svelte.js';

export interface TextRiseParams {
    direction?: 'in' | 'out';
    index?: number;
}

const ENTER_MS = 500;
const LEAVE_MS = 150;
const STAGGER_MS = 40;
const DISTANCE_PX = 12;
const BLUR_PX = 3;

/** cubic-bezier(0.22, 1, 0.36, 1) — the same curve as `--easing-spring`. */
function smoothOut(t: number): number {
    // Solve the bezier's x for t with a few Newton steps, then return y.
    const cx = 3 * 0.22, bx = 3 * (0.36 - 0.22) - cx, ax = 1 - cx - bx;
    const cy = 3 * 1, by = 3 * (1 - 1) - cy, ay = 1 - cy - by;
    let u = t;
    for (let i = 0; i < 6; i++) {
        const x = ((ax * u + bx) * u + cx) * u - t;
        const dx = (3 * ax * u + 2 * bx) * u + cx;
        if (Math.abs(dx) < 1e-6) break;
        u -= x / dx;
    }
    return ((ay * u + by) * u + cy) * u;
}

export function textRise(_node: Element, params?: TextRiseParams) {
    const {direction = 'in', index = 0} = params ?? {};

    if (direction === 'out') {
        return {
            duration: motionDuration(LEAVE_MS),
            css: (t: number) => `opacity: ${t};`,
        };
    }

    return {
        delay: motionDuration(index * STAGGER_MS),
        duration: motionDuration(ENTER_MS),
        easing: smoothOut,
        css: (t: number, u: number) => `
            opacity: ${t};
            transform: translateY(${u * DISTANCE_PX}px);
            filter: blur(${u * BLUR_PX}px);
        `,
    };
}
