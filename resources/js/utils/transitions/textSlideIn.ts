/**
 * Svelte action that slides a label over to its next value horizontally: the old text leaves
 * to the left and fades, the new text comes in from the right out of a soft blur. The
 * element's width eases from the old text to the new one, so a pill around the label resizes
 * smoothly instead of jumping.
 *
 * Follows the "page side-by-side" and "card resize" patterns from transitions.dev (250ms, 8px
 * on a smooth ease-out, 300ms resize). The action owns the element's text: render the node
 * empty and pass the label as the parameter. The first value is set without animation; with
 * reduced motion every change is an instant swap.
 *
 * @param node - The label element (provided by Svelte). Must be able to take a width.
 * @param text - The text to show; each update slides over to it.
 *
 * @example
 * <span class="label" use:textSlideIn={current.label}></span>
 */
import {motionDuration} from '$lib/utils/transitions/reducedMotion.svelte.js';

const ENTER_MS = 250;
const LEAVE_MS = 150;
const RESIZE_MS = 300;
const DISTANCE_PX = 8;
const BLUR_PX = 2;
const EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';

export function textSlideIn(node: HTMLElement, text: string) {
    let shown = text;
    let running: Animation[] = [];
    node.textContent = text;

    function settle(): void {
        running.forEach(animation => animation.cancel());
        running = [];
        node.textContent = shown;
        node.style.removeProperty('position');
        node.style.removeProperty('text-overflow');
    }

    function update(next: string): void {
        const prev = shown;
        if (prev === next) {
            return;
        }
        // Measured before settling, so an interrupted resize continues from where it is.
        const fromWidth = node.getBoundingClientRect().width;
        shown = next;
        settle();

        const duration = motionDuration(ENTER_MS);
        if (!duration) {
            return;
        }
        const toWidth = node.getBoundingClientRect().width;

        const incoming = document.createElement('span');
        incoming.style.cssText = 'display: inline-block; white-space: pre;';
        incoming.textContent = next;
        const outgoing = document.createElement('span');
        outgoing.style.cssText = 'position: absolute; top: 0; left: 0; white-space: pre;';
        outgoing.textContent = prev;
        node.replaceChildren(incoming, outgoing);
        node.style.position = 'relative';
        // An ellipsis would replace the whole incoming block while the box is still narrow.
        node.style.textOverflow = 'clip';

        running = [
            incoming.animate([
                {transform: `translateX(${DISTANCE_PX}px)`, opacity: 0, filter: `blur(${BLUR_PX}px)`},
                {transform: 'translateX(0)', opacity: 1, filter: 'blur(0)'},
            ], {duration, easing: EASE}),
            outgoing.animate([
                {transform: 'translateX(0)', opacity: 1},
                {transform: `translateX(-${DISTANCE_PX}px)`, opacity: 0},
            ], {duration: motionDuration(LEAVE_MS), easing: EASE, fill: 'forwards'}),
            node.animate(
                [{width: `${fromWidth}px`}, {width: `${toWidth}px`}],
                {duration: motionDuration(RESIZE_MS), easing: EASE}
            ),
        ];

        // Once settled, put the plain text back so the label can ellipsize again.
        const settled = running;
        Promise.all(settled.map(animation => animation.finished)).then(() => {
            if (running === settled) {
                settle();
            }
        }, () => {});
    }

    return {
        update,
        destroy() {
            running.forEach(animation => animation.cancel());
            running = [];
        },
    };
}
