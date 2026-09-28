import {BUILDER_STEPS, type BuilderStep} from "./builderValidationRules.js";

/**
 * How far the builder flow may currently be navigated: the index of the first
 * step with an unfilled required field (see
 * `BuilderValidatorContext.firstIncompleteStep`). Published by the builder's
 * step footer while it is mounted.
 *
 * Module state rather than read off `BuilderContext`, for the same reason as
 * `builderReturn.ts`: the sidebar that greys out locked steps is part of the
 * app sidebar, outside the builder layout's subtree.
 */
export const builderProgress = $state({reachable: BUILDER_STEPS.length as number});

/** Whether `step` lies past the first incomplete step and can't be opened yet. */
export function isBuilderStepLocked(step: BuilderStep): boolean {
    return BUILDER_STEPS.indexOf(step) > builderProgress.reachable;
}
