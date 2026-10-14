/**
 * Svelte CSS transition for a chip in a horizontal row: the pill unrolls from zero width
 * while it scales up and fades in, so switching one on reads as a small pop rather than a
 * chip blinking into existence. Because the width is animated in real layout, whatever sits
 * next to the chip glides aside instead of jumping. Leave is the same motion, faster.
 *
 * Shared by the composer's tool chips and by the assistant tags next to the model picker.
 * A chip that plays its own arrival animation on the inside (`MentionChip` reveals its
 * handle and then fills the pill in) should turn the overshoot off and start near its final
 * size, so the row's motion stays a plain unroll and doesn't compete with what follows it.
 *
 * @param node - The element being transitioned (provided by Svelte).
 * @param params.direction - `'in'` (enter, default) or `'out'` (leave).
 * @param params.scaleFrom - Scale the chip starts at, growing to 1. `1` disables the scale.
 * @param params.overshoot - Whether the enter swings past its final width and settles back
 *   (`backOut`). Turn it off for a chip whose neighbours should not be nudged twice.
 * @param params.duration - Overrides the duration in ms (default 320 entering, 180 leaving).
 *
 * @example
 * // Tool chip: a pop with overshoot.
 * <span in:chipPop out:chipPop={{direction: 'out'}}>…</span>
 *
 * @example
 * // Assistant tag: a plain unroll, since the chip animates its own arrival afterwards.
 * <span in:chipPop={{duration: 280, scaleFrom: 0.94, overshoot: false}}>…</span>
 */
import {backOut, cubicIn, cubicOut} from 'svelte/easing';

export interface ChipPopParams {
    direction?: 'in' | 'out';
    scaleFrom?: number;
    overshoot?: boolean;
    duration?: number;
}

export function chipPop(node: Element, params?: ChipPopParams) {
    const {direction = 'in', scaleFrom = 0.7, overshoot = true, duration} = params ?? {};
    const width = node.getBoundingClientRect().width;
    const entering = direction === 'in';
    return {
        duration: duration ?? (entering ? 320 : 180),
        easing: entering ? (overshoot ? backOut : cubicOut) : cubicIn,
        css: (t: number) => `
            width: ${Math.max(0, t * width)}px;
            opacity: ${Math.min(1, t * 1.6)};
            transform: scale(${scaleFrom + (1 - scaleFrom) * t});
            overflow: hidden;
            white-space: nowrap;
        `,
    };
}
