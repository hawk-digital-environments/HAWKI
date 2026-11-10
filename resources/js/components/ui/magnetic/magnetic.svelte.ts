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
 *
 * Every attached element shares one window listener and one update per
 * animation frame (see `tracker` below): a grid full of magnets costs a
 * single `pointermove` handler and one layout-read pass per frame while the
 * pointer moves, not a handler and a read per element per event.
 */
import type {Attachment} from 'svelte/attachments';
import {Spring} from 'svelte/motion';
import {useReducedMotion, type ReducedMotionState} from '$lib/utils/transitions/reducedMotion.svelte';

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

/** One attached element and its tuning. */
interface Magnet {
    el: HTMLElement;
    spring: Spring<{x: number, y: number}>;
    maxX: number;
    maxY: number;
    reach: number;
    reducedMotion: ReducedMotionState;
}

/** Latest pointer position; null while none has been seen (or it left the window). */
let pointer: {x: number, y: number} | null = null;
/** Pending rAF id, 0 while none is scheduled. */
let frame = 0;
const magnets = new Set<Magnet>();

function release(magnet: Magnet): void {
    if (magnet.spring.target.x !== 0 || magnet.spring.target.y !== 0) {
        magnet.spring.target = {x: 0, y: 0};
    }
}

/** Pulls one magnet towards the pointer — the same math the per-element
 *  version used, run at most once per frame. */
function pull(magnet: Magnet, at: {x: number, y: number}): void {
    if (magnet.reducedMotion.current) {
        release(magnet);
        return;
    }
    const {el, spring, maxX, maxY, reach} = magnet;
    const r = el.getBoundingClientRect();
    // Measured against the untranslated box, so the pull can't feed
    // back into itself.
    const halfW = r.width / 2;
    const halfH = r.height / 2;
    const dx = at.x - (r.left - spring.current.x * maxX + halfW);
    const dy = at.y - (r.top - spring.current.y * maxY + halfH);

    // Distance past the edge (0 inside): full pull inside, fading to
    // none at `reach`.
    const gap = Math.hypot(Math.max(0, Math.abs(dx) - halfW), Math.max(0, Math.abs(dy) - halfH));
    if (gap > reach || (reach === 0 && gap > 0)) {
        release(magnet);
        return;
    }
    const strength = reach === 0 ? 1 : (1 - gap / reach) ** 2;
    spring.target = {x: clamp(dx / halfW) * strength, y: clamp(dy / halfH) * strength};
}

function tick(): void {
    frame = 0;
    if (!pointer) return;
    for (const magnet of magnets) pull(magnet, pointer);
}

function wake(): void {
    if (!frame) frame = requestAnimationFrame(tick);
}

function onPointerMove(event: PointerEvent): void {
    if (event.pointerType === 'touch') return;
    pointer = {x: event.clientX, y: event.clientY};
    wake();
}

// Released when the pointer leaves the window (no element to move to).
function onPointerOut(event: PointerEvent): void {
    if (event.relatedTarget) return;
    pointer = null;
    for (const magnet of magnets) release(magnet);
}

/** Window listeners live only while at least one magnet is attached. */
function retainTracker(): void {
    if (magnets.size === 1) {
        window.addEventListener('pointermove', onPointerMove, {passive: true});
        window.addEventListener('pointerout', onPointerOut);
    }
}

function releaseTracker(): void {
    if (magnets.size === 0) {
        if (frame) cancelAnimationFrame(frame);
        frame = 0;
        pointer = null;
        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerout', onPointerOut);
    }
}

export function magnetic(options: MagneticOptions = {}): Attachment<HTMLElement> {
    const {maxX = 4, maxY = 3, reach = 32, stiffness = 0.2, damping = 0.5} = options;

    return (el) => {
        const reducedMotion = useReducedMotion();
        const magnet: Magnet = {
            el,
            spring: new Spring({x: 0, y: 0}, {stiffness, damping}),
            maxX,
            maxY,
            reach,
            reducedMotion,
        };

        $effect(() => {
            const {x, y} = magnet.spring.current;
            el.style.translate = x || y ? `${x * maxX}px ${y * maxY}px` : '';
            el.style.setProperty('--magnet-x', String(x));
            el.style.setProperty('--magnet-y', String(y));
        });

        magnets.add(magnet);
        retainTracker();

        return () => {
            magnets.delete(magnet);
            releaseTracker();
            el.style.translate = '';
            el.style.removeProperty('--magnet-x');
            el.style.removeProperty('--magnet-y');
        };
    };
}

function clamp(v: number): number {
    return Math.max(-1, Math.min(1, v));
}
