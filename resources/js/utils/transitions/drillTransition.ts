/**
 * Svelte CSS transition for a drill-down level swap: one panel slides out
 * horizontally while its replacement slides in from the opposite side, like
 * a mobile navigation stack. The slide is combined with an opacity
 * crossfade (matching svelte `fly`'s defaults), so the departing level
 * dissolves rather than moving as a solid panel.
 *
 * Used wherever the UI replaces one level of navigation with another — the
 * sidebar's drill-down (e.g. the assistants builder sections) and dropdown
 * detail views. Both levels share one grid cell (see the `.nav-stack` /
 * `.viewport` patterns), so the outgoing panel's unmount never collapses
 * the layout mid-slide.
 *
 * Scale the `distance` to the surface: a full-height sidebar needs a wide,
 * clearly readable travel (the default `180`), a compact popover only a
 * small nudge (e.g. `16`).
 *
 * Honours `prefers-reduced-motion` by collapsing to an instant swap
 * (`duration: 0`) — the level change stays functional, only the travel is
 * dropped.
 *
 * @param node - The element being transitioned (provided by Svelte).
 * @param params.direction - `'deeper'` (default) flies along +x — a lower
 *   level arriving from the right / the upper level leaving to the right —
 *   `'back'` flies along -x for returning up.
 * @param params.distance - Horizontal travel in px. Defaults to `180`.
 * @param params.duration - Duration in ms. Defaults to `200`.
 *
 * @example
 * // Sidebar drill-down: builder sections slide in from the right …
 * <div class="nav-level" in:drillTransition out:drillTransition={{direction: 'back'}}>
 *
 * // Popover detail view: same drill, compact scale.
 * <div class="view" in:drillTransition={{distance: 16, duration: 150}}
 *      out:drillTransition={{direction: 'back', distance: 16, duration: 150}}>
 */
import {cubicOut} from 'svelte/easing';

export function drillTransition(
    _node: Element,
    params?: {direction?: 'deeper' | 'back', distance?: number, duration?: number}
) {
    const {direction = 'deeper', distance = 180, duration = 200} = params ?? {};
    const x = direction === 'deeper' ? distance : -distance;
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    return {
        duration: reducedMotion ? 0 : duration,
        easing: cubicOut,
        css: (t: number) => `
            transform: translateX(${x * (1 - t)}px);
            opacity: ${t};
        `
    };
}
