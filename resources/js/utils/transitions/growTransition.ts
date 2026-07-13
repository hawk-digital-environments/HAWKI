/**
 * Svelte CSS transition that expands or collapses an element by animating its
 * height (default) or width from 0 to its natural size, while fading opacity
 * and scaling padding/margin proportionally so the element doesn't jump.
 *
 * Used wherever an element should appear to "grow out of" or "shrink into"
 * its container — for example the `RadialProgress` ring that slides in
 * horizontally when a file upload begins.
 *
 * @param node - The element being transitioned (provided by Svelte).
 * @param params.direction - `'in'` (enter, default) or `'out'` (leave).
 *   Enter uses a spring overshoot; leave uses `cubicOut`.
 * @param params.mode - `'vertical'` (default, animates height) or
 *   `'horizontal'` (animates width).
 * @param params.overshoot - Tension of the enter's `backOut` curve, i.e. how far it swings
 *   past its natural size before settling back. `0` is a plain ease-out; `0.6` (the default)
 *   peaks about 1% over, which on a few dozen pixels is under half a pixel and reads as no
 *   spring at all; `1.3` peaks around 6% and reads as a small one; Svelte's own `backOut`
 *   sits near `1.7` (~10%). Leaving is always `cubicOut` — swinging on the way out would
 *   take the size below zero and just clip.
 *
 * @example
 * // Vertical grow (default)
 * <div transition:growTransition>…</div>
 *
 * // Horizontal grow, enter only
 * <span in:growTransition={{mode: 'horizontal'}}>…</span>
 *
 * // Vertical grow that visibly springs into place
 * <div transition:growTransition={{overshoot: 1.3}}>…</div>
 */
import {cubicOut} from 'svelte/easing';

/** `backOut` with the tension left open, so a caller can pick how much of a swing it wants. */
function backOutWith(s: number) {
    return (t: number) => 1 + (s + 1) * Math.pow(t - 1, 3) + s * Math.pow(t - 1, 2);
}

/**
 * Parses a CSS length into pixels, falling back to 0 when the value cannot be
 * parsed. `getComputedStyle` returns the empty string for layout properties
 * while an element is inside a `display: none` subtree, and `parseFloat('')`
 * is `NaN` — which would otherwise end up as invalid `NaNpx` keyframes.
 */
function px(value: string): number {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

export interface GrowTransitionParams {
    direction?: 'in' | 'out';
    mode?: 'horizontal' | 'vertical';
    overshoot?: number;
}

export function growTransition(node: Element, params?: GrowTransitionParams) {
    const height = node.scrollHeight;
    const style = getComputedStyle(node);
    const {direction = 'in', mode = 'vertical', overshoot = 0.6} = params ?? {};
    const easing = direction === 'in' ? backOutWith(overshoot) : cubicOut;
    const duration = direction === 'in' ? 300 : 220;

    if (mode === 'horizontal') {
        const width = node.scrollWidth;
        const paddingLeft = px(style.paddingLeft);
        const paddingRight = px(style.paddingRight);
        const marginLeft = px(style.marginLeft);
        const marginRight = px(style.marginRight);
        return {
            duration,
            easing,
            css: (t: number) => `
                overflow: hidden;
                opacity: ${t};
                width: ${Math.max(0, t * width)}px;
                padding-left: ${Math.max(0, t * paddingLeft)}px;
                padding-right: ${Math.max(0, t * paddingRight)}px;
                margin-left: ${Math.max(0, t * marginLeft)}px;
                margin-right: ${Math.max(0, t * marginRight)}px;
                transform: translateX(${(1 - t) * -(paddingLeft + paddingRight)}px);
            `
        };
    }

    const paddingTop = px(style.paddingTop);
    const paddingBottom = px(style.paddingBottom);
    const marginTop = px(style.marginTop);
    const marginBottom = px(style.marginBottom);
    return {
        duration,
        easing,
        css: (t: number) => `
            overflow: hidden;
            opacity: ${t};
            height: ${Math.max(0, t * height)}px;
            padding-top: ${Math.max(0, t * paddingTop)}px;
            padding-bottom: ${Math.max(0, t * paddingBottom)}px;
            margin-top: ${Math.max(0, t * marginTop)}px;
            margin-bottom: ${Math.max(0, t * marginBottom)}px;
            transform: translateY(${(1 - t) * -(paddingTop + paddingBottom)}px);
        `
    };
}
