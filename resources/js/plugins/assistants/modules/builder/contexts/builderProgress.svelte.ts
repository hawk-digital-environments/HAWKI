import {BUILDER_STEPS, COMPLETENESS_RULES, type BuilderStep} from "./builderValidationRules.js";

/**
 * How far the builder flow has come, for the sidebar's step checklist:
 * - `reachable`: index of the first step with an unfilled required field (see
 *   `BuilderValidatorContext.firstIncompleteStep`); later steps are locked.
 * - `complete`: per step, whether all of its required fields are satisfied.
 * - `ratio`: per step, the share (0–1) of its required fields that are satisfied.
 * - `furthest`: index of the furthest step the user has opened.
 * Published by the builder's step footer while it is mounted.
 *
 * Module state rather than read off `BuilderContext`, for the same reason as
 * `builderReturn.ts`: the sidebar that shows the checklist is part of the
 * app sidebar, outside the builder layout's subtree.
 */
export const builderProgress = $state({
    reachable: BUILDER_STEPS.length as number,
    complete: {} as Partial<Record<BuilderStep, boolean>>,
    ratio: {} as Partial<Record<BuilderStep, number>>,
    furthest: 0,
});

/** Whether `step` lies past the first incomplete step and can't be opened yet. */
export function isBuilderStepLocked(step: BuilderStep): boolean {
    return BUILDER_STEPS.indexOf(step) > builderProgress.reachable;
}

/**
 * Whether a checklist step is done: its required fields are filled and —
 * for steps without required fields (knowledge) — the user has moved past it.
 * Publish is never "done"; it is the action the checklist leads to.
 */
export function isBuilderStepDone(step: BuilderStep): boolean {
    if (step === 'publish' || builderProgress.complete[step] === false) return false;
    const hasRules = COMPLETENESS_RULES.some(rule => rule.step === step);
    return hasRules
        ? builderProgress.complete[step] === true
        : builderProgress.furthest > BUILDER_STEPS.indexOf(step);
}
