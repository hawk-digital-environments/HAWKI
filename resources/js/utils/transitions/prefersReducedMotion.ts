/** Read the user's current operating-system motion preference. */
export function prefersReducedMotion(): boolean {
    return typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Return an animation duration that becomes immediate in reduced-motion mode. */
export function motionDuration(duration: number): number {
    return prefersReducedMotion() ? 0 : duration;
}
