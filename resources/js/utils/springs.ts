/**
 * Settings for Svelte's `Spring`, one per motion tier. Both are critically damped, so
 * they land without overshoot: `fast` settles in about 80ms, for small things like a
 * hover highlight or a label sliding open; `moderate` in about 160ms, for a selection
 * moving between items. The bigger the thing that moves, the slower the tier.
 *
 * @example
 * const width = new Spring(0, springs.fast);
 */
export const springs = {
    fast: {stiffness: 0.53, damping: 0.93},
    moderate: {stiffness: 0.2, damping: 0.7}
} as const;
