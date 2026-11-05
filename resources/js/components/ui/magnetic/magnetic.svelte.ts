/**
 * Magnetic hover as an attachment: `<div {@attach magnetic()}>`.
 *
 * The element leans toward the pointer on a spring, so it trails the cursor
 * and settles back with a little give. It already starts to react within
 * `reach` px of its edge, fading in as the pointer approaches, so moving onto
 * it is a pull rather than a switch.
 *
 * The attachment sets `translate` inline (don't add a CSS transition for it —
 * it would fight the spring) and publishes the normalised pull as
 * `--magnet-x` / `--magnet-y` (-1…1), so children can parallax on top of it:
 *
 *     .glyph { translate: calc(var(--magnet-x, 0) * 4px) calc(var(--magnet-y, 0) * 2.5px); }
 *
 * Mouse/pen only (on touch a "hover" is a tap) and off under reduced motion.
 */
import type {Attachment} from 'svelte/attachments';
import {Spring} from 'svelte/motion';
import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte';

export interface MagneticOptions {
    /** Furthest the element moves sideways, in px. */
    maxX?: number;
    /** Furthest the element moves up/down, in px. */
    maxY?: number;
    /** Distance past the element's edge (px) at which the pull starts. 0 = only while over it. */
    reach?: number;
    /** Spring tuning, see `svelte/motion`'s Spring. */
    stiffness?: number;
    damping?: number;
}

export function magnetic(options: MagneticOptions = {}): Attachment<HTMLElement> {
    const {maxX = 4, maxY = 3, reach = 32, stiffness = 0.2, damping = 0.5} = options;

    return (el) => {
        const reducedMotion = useReducedMotion();
        const pull = new Spring({x: 0, y: 0}, {stiffness, damping});

        $effect(() => {
            const {x, y} = pull.current;
            el.style.translate = x || y ? `${x * maxX}px ${y * maxY}px` : '';
            el.style.setProperty('--magnet-x', String(x));
            el.style.setProperty('--magnet-y', String(y));
        });

        const release = () => {
            if (pull.target.x !== 0 || pull.target.y !== 0) pull.target = {x: 0, y: 0};
        };

        // On the window, because the reach extends past the element's box.
        const onMove = (event: PointerEvent) => {
            if (event.pointerType === 'touch' || reducedMotion.current) {
                release();
                return;
            }
            const r = el.getBoundingClientRect();
            // Measured against the untranslated box, so the pull can't feed
            // back into itself.
            const halfW = r.width / 2;
            const halfH = r.height / 2;
            const dx = event.clientX - (r.left - pull.current.x * maxX + halfW);
            const dy = event.clientY - (r.top - pull.current.y * maxY + halfH);

            // Distance past the edge (0 inside): full pull inside, fading to
            // none at `reach`.
            const gap = Math.hypot(Math.max(0, Math.abs(dx) - halfW), Math.max(0, Math.abs(dy) - halfH));
            if (gap > reach || (reach === 0 && gap > 0)) {
                release();
                return;
            }
            const strength = reach === 0 ? 1 : (1 - gap / reach) ** 2;
            pull.target = {x: clamp(dx / halfW) * strength, y: clamp(dy / halfH) * strength};
        };

        // Released when the pointer leaves the window (no element to move to).
        const onOut = (event: PointerEvent) => {
            if (!event.relatedTarget) release();
        };

        window.addEventListener('pointermove', onMove, {passive: true});
        window.addEventListener('pointerout', onOut);

        return () => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerout', onOut);
            el.style.translate = '';
            el.style.removeProperty('--magnet-x');
            el.style.removeProperty('--magnet-y');
        };
    };
}

function clamp(v: number): number {
    return Math.max(-1, Math.min(1, v));
}
