export interface ReducedMotionState {
    readonly current: boolean;
}

class ReducedMotionPreference implements ReducedMotionState {
    #current = $state(false);

    constructor() {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return;

        const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.#current = mediaQuery.matches;
        mediaQuery.addEventListener('change', event => {
            this.#current = event.matches;
        });
    }

    get current(): boolean {
        return this.#current;
    }
}

const reducedMotion = new ReducedMotionPreference();

/** Return the shared reactive operating-system motion preference. */
export function useReducedMotion(): ReducedMotionState {
    return reducedMotion;
}

/** Return an animation duration that becomes immediate in reduced-motion mode. */
export function motionDuration(duration: number): number {
    return reducedMotion.current ? 0 : duration;
}
