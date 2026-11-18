/**
 * Svelte CSS transition that blur-replaces an icon: the old one fades out shrinking and
 * blurring while the new one does the reverse in the same slot. Stack both in one grid cell,
 * since they overlap for the duration.
 *
 * Follows the "icon swap" pattern from transitions.dev (250ms, 2px blur, 0.25 start scale,
 * ease-in-out). The duration collapses to zero when the user prefers reduced motion.
 *
 * @param _node - The element being transitioned (provided by Svelte).
 *
 * @example
 * {#key Icon}
 *     <span class="slot" transition:iconSwap><Icon/></span>
 * {/key}
 */
import {cubicInOut} from 'svelte/easing';
import {motionDuration} from '$lib/utils/transitions/reducedMotion.svelte.js';

const DURATION_MS = 250;
const BLUR_PX = 2;
const START_SCALE = 0.25;

export function iconSwap(_node: Element) {
    return {
        duration: motionDuration(DURATION_MS),
        easing: cubicInOut,
        css: (t: number, u: number) => `
            opacity: ${t};
            filter: blur(${u * BLUR_PX}px);
            transform: scale(${START_SCALE + (1 - START_SCALE) * t});
        `,
    };
}
