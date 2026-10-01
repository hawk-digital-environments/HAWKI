import {motionDuration} from '$lib/utils/transitions/reducedMotion.svelte.js';
import {cubicIn} from 'svelte/easing';

/**
 * Svelte CSS transition for a list row that is deleted: the row first
 * becomes invisible from right to left (a clip-path wipe with a slight
 * drift to the left), then collapses so the remaining rows close the gap.
 *
 * Follows the M3 exit guidance ("exit the screen permanently"): an
 * accelerate-style curve (`cubicIn` approximates M3's emphasized
 * accelerate `cubic-bezier(0.3, 0, 0.8, 0.15)`) — the exit starts at rest
 * and ends at peak velocity — over short total duration (~480ms; exits
 * and collapses get the short end of the M3 duration scale). Exits never
 * overshoot: bounce is reserved for elements entering or moving on screen.
 *
 * The collapse phase animates the measured height and paddings to zero and
 * drives `margin-bottom` to `-(gap)` so the parent's flex `gap` is eaten
 * along with the row — neighbours close seamlessly instead of leaving a
 * doubled gap behind.
 *
 * Under reduced motion the duration resolves to 0, removing the row at
 * once (via {@link motionDuration}).
 *
 * @param node - the element being transitioned (provided by Svelte).
 * @param params.gap - the parent list's flex gap in px, consumed by the
 *   collapse's negative end margin. @default 8
 *
 * @example
 * {#each rows as row (row.uuid)}
 *     <div class="row" out:wipeCollapse>…</div>
 * {/each}
 */
export interface WipeCollapseParams {
    gap?: number;
}

/** Share of the timeline spent wiping before the collapse starts. */
const WIPE_SHARE = 0.55;

export function wipeCollapse(node: Element, params?: WipeCollapseParams) {
    const {gap = 8} = params ?? {};
    const height = node.scrollHeight;
    const style = getComputedStyle(node);
    const paddings = [style.paddingTop, style.paddingBottom, style.paddingLeft, style.paddingRight]
        .map((value) => {
            const parsed = parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        });
    const [paddingTop, paddingBottom, paddingLeft, paddingRight] = paddings;
    const marginBottom = parseFloat(style.marginBottom);

    return {
        duration: motionDuration(480),
        easing: cubicIn,
        css: (t: number) => {
            const progress = 1 - t;
            const wipe = Math.min(1, progress / WIPE_SHARE);
            const collapse = Math.max(0, (progress - WIPE_SHARE) / (1 - WIPE_SHARE));
            const settled = 1 - collapse;

            return `
                overflow: hidden;
                pointer-events: none;
                clip-path: inset(0 ${wipe * 100}% 0 0);
                transform: translateX(${-12 * wipe}px);
                height: ${Math.max(0, height * settled)}px;
                padding-top: ${Math.max(0, paddingTop * settled)}px;
                padding-bottom: ${Math.max(0, paddingBottom * settled)}px;
                padding-left: ${Math.max(0, paddingLeft * settled)}px;
                padding-right: ${Math.max(0, paddingRight * settled)}px;
                margin-bottom: ${(Number.isFinite(marginBottom) ? marginBottom : 0) * settled - collapse * gap}px;
            `;
        }
    };
}
