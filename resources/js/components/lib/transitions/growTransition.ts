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
 *   Enter uses a gentle spring overshoot; leave uses `cubicOut`.
 * @param params.mode - `'vertical'` (default, animates height) or
 *   `'horizontal'` (animates width).
 *
 * @example
 * // Vertical grow (default)
 * <div transition:growTransition>…</div>
 *
 * // Horizontal grow, enter only
 * <span in:growTransition={{mode: 'horizontal'}}>…</span>
 */
import {cubicOut} from 'svelte/easing';

function gentleBackOut(t: number) {
    const s = 0.6;
    return 1 + (s + 1) * Math.pow(t - 1, 3) + s * Math.pow(t - 1, 2);
}

export function growTransition(node: Element, params?: { direction?: 'in' | 'out', mode?: 'horizontal' | 'vertical' }) {
    const height = node.scrollHeight;
    const style = getComputedStyle(node);
    const paddingTop = parseFloat(style.paddingTop);
    const paddingBottom = parseFloat(style.paddingBottom);
    const marginTop = parseFloat(style.marginTop);
    const marginBottom = parseFloat(style.marginBottom);
    const {direction = 'in', mode = 'vertical'} = params ?? {};
    const easing = direction === 'in' ? gentleBackOut : cubicOut;
    return {
        duration: direction === 'in' ? 300 : 220,
        easing,
        css: (t: number) => {
            if (mode === 'horizontal') {
                return `
                    overflow: hidden;
                    opacity: ${t};
                    width: ${Math.max(0, t * node.scrollWidth)}px;
                    padding-left: ${Math.max(0, t * parseFloat(style.paddingLeft))}px;
                    padding-right: ${Math.max(0, t * parseFloat(style.paddingRight))}px;
                    margin-left: ${Math.max(0, t * parseFloat(style.marginLeft))}px;
                    margin-right: ${Math.max(0, t * parseFloat(style.marginRight))}px;
                    transform: translateX(${(1 - t) * -(parseFloat(style.paddingLeft) + parseFloat(style.paddingRight))}px);
                `;
            }

            return `
                overflow: hidden;
                opacity: ${t};
                height: ${Math.max(0, t * height)}px;
                padding-top: ${Math.max(0, t * paddingTop)}px;
                padding-bottom: ${Math.max(0, t * paddingBottom)}px;
                margin-top: ${Math.max(0, t * marginTop)}px;
                margin-bottom: ${Math.max(0, t * marginBottom)}px;
                transform: translateY(${(1 - t) * -(paddingTop + paddingBottom)}px);
            `;
        }
    };
}
