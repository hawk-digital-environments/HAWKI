/**
 * Svelte action that counts a short label over to its next value: the leading characters
 * the old and the new text share stay still, every character after them slides up out of a
 * soft blur one after another, like digits on a counter ("GPT-4o" → "GPT-4.1": "GPT-4" stays,
 * ".1" rolls in). The element's width eases from the old text to the new one, so a pill
 * around the label resizes smoothly instead of jumping.
 *
 * Follows the "number pop-in" pattern from transitions.dev (500ms, 8px rise, 2px blur on a
 * slightly springy ease-out), with a short per-character stagger so a whole word reads as a
 * count rather than one block. The action owns the element's text: render the node empty and
 * pass the label as the parameter. The first value is set without animation; with reduced
 * motion every change is an instant swap.
 *
 * @param node - The label element (provided by Svelte).
 * @param text - The text to show; each update counts over to it.
 *
 * @example
 * <span class="label" use:textPopIn={current?.label ?? placeholder}></span>
 */
import {motionDuration} from '$lib/utils/transitions/reducedMotion.svelte.js';

const DURATION_MS = 500;
const RESIZE_MS = 250;
const STAGGER_MS = 25;
/** Caps the stagger so a long name still settles quickly. */
const MAX_STAGGER_STEPS = 10;
const DISTANCE_PX = 8;
const BLUR_PX = 2;
const EASE = 'cubic-bezier(0.34, 1.45, 0.64, 1)';
const RESIZE_EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';

export function textPopIn(node: HTMLElement, text: string) {
    let shown = text;
    let running: Animation[] = [];
    node.textContent = text;

    function cancel(): void {
        running.forEach(animation => animation.cancel());
        running = [];
    }

    function update(next: string): void {
        const prev = shown;
        if (prev === next) {
            return;
        }
        shown = next;
        // Measured before cancelling, so an interrupted resize continues from where it is.
        const fromWidth = node.getBoundingClientRect().width;
        cancel();

        const duration = motionDuration(DURATION_MS);
        if (!duration) {
            node.textContent = next;
            return;
        }

        let same = 0;
        while (same < prev.length && same < next.length && prev[same] === next[same]) same++;

        // Shared lead as plain text; one inline-block per changed character so each can rise.
        node.textContent = next.slice(0, same);
        const chars = [...next.slice(same)].map(ch => {
            const span = document.createElement('span');
            span.style.cssText = 'display: inline-block; white-space: pre;';
            span.textContent = ch;
            return span;
        });
        node.append(...chars);
        const toWidth = node.getBoundingClientRect().width;

        running = chars.map((span, i) => span.animate([
            {transform: `translateY(${DISTANCE_PX}px)`, opacity: 0, filter: `blur(${BLUR_PX}px)`},
            {transform: 'translateY(0)', opacity: 1, filter: 'blur(0)'},
        ], {duration, easing: EASE, delay: Math.min(i, MAX_STAGGER_STEPS) * STAGGER_MS, fill: 'backwards'}));
        running.push(node.animate(
            [{width: `${fromWidth}px`}, {width: `${toWidth}px`}],
            {duration: motionDuration(RESIZE_MS), easing: RESIZE_EASE}
        ));

        // Once settled, put the plain text back so the label can ellipsize again.
        const settled = running;
        Promise.all(settled.map(animation => animation.finished)).then(() => {
            if (running === settled) {
                running = [];
                node.textContent = next;
            }
        }, () => {});
    }

    return {
        update,
        destroy: cancel,
    };
}
