import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";
import {createEmptyAssistant} from "$plugins/assistants/api/serializers/assistantSerializer";
import {apiFieldToAssistantKey} from "$plugins/assistants/api/serializers/apiFieldSerializer";
import {ApiError} from "$plugins/assistants/api/errors";
import {valuesEqual, IDENTITY_KEYS} from "./builderUtils.js";
import {ValidationState} from "$plugins/assistants/types/enums/ValidationState";
import type {BuilderMode} from "./BuilderContext.svelte.js";
import {
    COMPLETENESS_RULES,
    TRIGGER_RULES,
    type CheckItem,
    type FieldErrorMap,
    type ReportGroup,
} from "./builderValidationRules.js";

export type {CheckItem, FieldErrorMap, ReportGroup} from "./builderValidationRules.js";

/** Thrown by {@link BuilderValidatorContext.validate} when required fields are
 *  not satisfied; `failures` carries the unsatisfied checks for reporting. */
export class ValidationError extends Error {
    constructor(public readonly failures: CheckItem[]) {
        super(`Assistant validation failed: ${failures.map(f => f.id).join(', ')}`);
        this.name = 'ValidationError';
    }
}

/**
 * Owns everything about whether the builder draft is *valid*:
 *  - change tracking versus the start of the editing session (for remix, the
 *    source assistant), used both for "audit triggers" and the remix rule;
 *  - server-side field errors (moved out of the store) surfaced inline by inputs;
 *  - client-side completeness checks for required fields, reported to the UI.
 *
 * Owned and constructed by {@link BuilderContext} (accessible as
 * `builder.validator`) rather than published as its own Svelte context: every
 * consumer that needs it already holds a `BuilderContext`. It reads the draft
 * via injected accessors instead of importing the builder context module
 * directly, so the two files don't need to import each other.
 */
export class BuilderValidatorContext {

    constructor(
        private readonly getDraft: () => Assistant,
        private readonly getMode: () => BuilderMode,
    ) {}

    /** Snapshot of the draft at the start of the editing session (for remix:
     *  the source assistant). Used to detect what the user changed this session. */
    sessionBaseline = $state<Assistant>(createEmptyAssistant());

    /** Server validation errors keyed by `Assistant` field, surfaced inline by
     *  each input. Populated on a failed save; cleared on edit / successful save. */
    fieldErrors = $state<FieldErrorMap>({});

    /** Snapshot `assistant` as the session baseline. `$state.snapshot` detaches
     *  it from the reactive proxy so later edits don't mutate the baseline. */
    init(assistant: Assistant): void {
        this.sessionBaseline = $state.snapshot(assistant) as Assistant;
        this.fieldErrors = {};
    }

    // ----- change tracking (vs. session start / remix source) -----

    readonly sessionChangedKeys = $derived.by(() => {
        const out = new Set<keyof Assistant>();
        const draft = this.getDraft();
        for (const key of Object.keys(draft) as (keyof Assistant)[]) {
            if (IDENTITY_KEYS.has(key)) continue;
            if (!valuesEqual(draft[key], this.sessionBaseline[key])) out.add(key);
        }
        return out;
    });

    isChanged(key: keyof Assistant): boolean {
        return this.sessionChangedKeys.has(key);
    }

    // ----- server-side field errors (moved here from the store) -----

    /** The inline server error for a field, if any. */
    errorFor(key: keyof Assistant): string | undefined {
        return this.fieldErrors[key];
    }

    clearError(...keys: (keyof Assistant)[]): void {
        if (!keys.some(k => k in this.fieldErrors)) return;
        const next = { ...this.fieldErrors };
        for (const key of keys) delete next[key];
        this.fieldErrors = next;
    }

    /** Route a failed save's validation errors to their fields. */
    recordServerErrors(err: unknown): void {
        const apiError = ApiError.from(err);
        const patch: FieldErrorMap = {};
        for (const fe of apiError.fieldErrors) {
            const key = apiFieldToAssistantKey(fe.field);
            if (key) patch[key] = fe.message;
        }
        if (Object.keys(patch).length) {
            this.fieldErrors = { ...this.fieldErrors, ...patch };
        }
    }

    // ----- client-side completeness / required-field validation -----

    /**
     * Per required-field result. A field is satisfied when it is filled —
     * except in remix mode, where it must have been *changed* from the source
     * (a remix starts as a copy, so "filled" is trivially true and "changed" is
     * the meaningful signal).
     */
    readonly completeness = $derived.by<CheckItem[]>(() => {
        const draft = this.getDraft();
        const remix = this.getMode() === 'remix';
        return COMPLETENESS_RULES.map(rule => {
            const ok = remix
                ? rule.keys.some(k => this.isChanged(k))
                : rule.isFilled(draft);
            return {
                id: rule.id,
                group: rule.group,
                ok,
                status: ok ? ValidationState.SAFE : (rule.failStatus ?? ValidationState.WARNING),
                label: ok ? rule.okLabel : rule.failLabel,
                description: rule.description,
            };
        });
    });

    /** Completeness items grouped by their section title, for rendering. */
    readonly completenessGroups = $derived.by<ReportGroup[]>(() => {
        const groups: ReportGroup[] = [];
        for (const item of this.completeness) {
            let group = groups.find(g => g.group === item.group);
            if (!group) { group = { group: item.group, items: [] }; groups.push(group); }
            group.items.push(item);
        }
        return groups;
    });

    /** Audit triggers: fields whose change this session flags a re-check. */
    readonly triggers = $derived.by<CheckItem[]>(() => {
        return TRIGGER_RULES.map(rule => {
            const changed = rule.keys.some(k => this.isChanged(k));
            return {
                id: rule.id,
                group: rule.group,
                ok: !changed,
                status: changed ? ValidationState.WARNING : ValidationState.SAFE,
                label: changed ? rule.changedLabel : rule.unchangedLabel,
                description: rule.description,
            };
        });
    });

    /** True when every required field is satisfied. */
    readonly isComplete = $derived(this.completeness.every(c => c.ok));

    /** True while any field has an outstanding server error. */
    readonly hasServerErrors = $derived(Object.keys(this.fieldErrors).length > 0);

    /** Overall validity: complete and free of server errors. */
    readonly isValid = $derived(this.isComplete && !this.hasServerErrors);

    /**
     * Assert the draft is complete; throws {@link ValidationError} listing the
     * unsatisfied required fields. Use before publish / submit actions.
     */
    validate(): void {
        const failures = this.completeness.filter(c => !c.ok);
        if (failures.length) throw new ValidationError(failures);
    }
}
