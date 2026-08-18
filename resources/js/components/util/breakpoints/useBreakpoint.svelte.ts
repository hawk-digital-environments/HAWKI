import {type BreakpointName, breakpointsQueries} from './breakpoints.js';

export interface BreakpointState {
    /**
     * Returns true if the current viewport matches the given breakpoint.
     * @param breakpoint
     */
    is(breakpoint: BreakpointName): boolean;

    /**
     * Returns an array of all breakpoints that match the current viewport.
     */
    matching(): BreakpointName[];
}

/**
 * Returns a reactive object that tracks the current viewport's matching breakpoints.
 *
 * @returns An object with methods to check if a breakpoint matches and to get all matching breakpoints.
 */
export function useBreakpoint(): BreakpointState {
    const supported = typeof window !== 'undefined' && typeof window.matchMedia === 'function';

    let matchStates = $state<Record<string, boolean>>(
        supported
            ? Object.fromEntries(
                Object.entries(breakpointsQueries).map(([k, q]) => [k, window.matchMedia(q).matches])
            )
            : {}
    );

    $effect(() => {
        if (!supported) return;
        const cleanups = (Object.entries(breakpointsQueries) as [string, string][]).map(
            ([key, query]) => {
                const mql = window.matchMedia(query);
                const onChange = (e: MediaQueryListEvent) => {
                    matchStates[key] = e.matches;
                };
                mql.addEventListener('change', onChange);
                return () => mql.removeEventListener('change', onChange);
            }
        );
        return () => cleanups.forEach(fn => fn());
    });

    return {
        is(breakpoint: BreakpointName): boolean {
            return matchStates[breakpoint] ?? false;
        },
        matching(): BreakpointName[] {
            return Object.entries(matchStates)
                .filter(([_, matches]) => matches)
                .map(([key, _]) => key as BreakpointName);
        }
    };
}
