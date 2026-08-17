import type {Assistant} from "$lib/plugins/assistants/types/assistant/Assistant";
import {createEmptyAssistant} from "$lib/plugins/assistants/api/serializers/assistantSerializer"
import {apiFieldToAssistantKey} from "$lib/plugins/assistants/api/serializers/apiFieldSerializer";
import {ApiError} from "$lib/plugins/assistants/api/errors";
import {valuesEqual, IDENTITY_KEYS} from "./assistantStoreUtils";
import {assistantBuilderStore} from "./AssistantBuilderStore.svelte.js";
import {ValidationState} from "$lib/plugins/assistants/types/enums/ValidationState";

/** Per-field error message, keyed by `Assistant` field. */
export type FieldErrorMap = Partial<Record<keyof Assistant, string>>;

/** A single line in a builder report (completenessCheckItem check or audit trigger). */
export interface CheckItem {
    id: string;
    group: string;
    label: string;
    description?: string;
    status: ValidationState;
    /** Whether the check passes (required field satisfied / no trigger). */
    ok: boolean;
}

/** Report items grouped under a section title, for rendering. */
export interface ReportGroup {
    group: string;
    items: CheckItem[];
}

/** A required-field completeness rule. */
interface CompletenessRule {
    id: string;
    group: string;
    /** Fields this rule concerns (used for the remix "changed" check). */
    keys: (keyof Assistant)[];
    /** Whether the field(s) hold an acceptable value. */
    isFilled: (a: Assistant) => boolean;
    okLabel: string;
    failLabel: string;
    description?: string;
    /** Status when not satisfied (default `warning`). */
    failStatus?: ValidationState;
}

/** A field whose change during the session flags a re-check. */
interface TriggerRule {
    id: string;
    group: string;
    keys: (keyof Assistant)[];
    changedLabel: string;
    unchangedLabel: string;
    description?: string;
}

const COMPLETENESS_RULES: CompletenessRule[] = [
    {
        id: 'name-handle',
        group: 'Allgemeine Informationen',
        keys: ['name', 'handle'],
        isFilled: a => !!(a.name && a.handle),
        okLabel: 'Name und Handle festgelegt',
        failLabel: 'Name und Handle noch nicht festgelegt',
    },
    {
        id: 'description',
        group: 'Allgemeine Informationen',
        keys: ['description'],
        isFilled: a => a.description !== '',
        okLabel: 'Beschreibung vollständig',
        failLabel: 'Beschreibung nicht vollständig!',
        description: 'Kernverhalten wurde angepasst',
    },
    {
        id: 'category',
        group: 'Allgemeine Informationen',
        keys: ['category'],
        isFilled: a => !!a.category,
        okLabel: 'Kategorie gewählt',
        failLabel: 'Kategorie nicht gewählt',
    },
    {
        id: 'system-prompt',
        group: 'Verhalten',
        keys: ['systemPrompt'],
        isFilled: a => !!a.systemPrompt,
        okLabel: 'System-Prompt definiert',
        failLabel: 'System-Prompt nicht definiert',
        failStatus: ValidationState.UNKNOWN,
    },
    {
        id: 'model',
        group: 'Model',
        keys: ['model'],
        isFilled: a => !!a.model,
        okLabel: 'Model Ausgewählt',
        failLabel: 'Model nicht ausgewählt!',
    },
];

const TRIGGER_RULES: TriggerRule[] = [
    {
        id: 'system-prompt-changed',
        group: 'Prüfungsauslöser',
        keys: ['systemPrompt'],
        changedLabel: 'System-Prompt geändert',
        unchangedLabel: 'System-Prompt unverändert',
        description: 'Kernverhalten wurde angepasst',
    },
    {
        id: 'files-changed',
        group: 'Prüfungsauslöser',
        keys: ['files'],
        changedLabel: 'Dateien geändert',
        unchangedLabel: 'Keine Dateiänderung',
    },
];

/** Thrown by {@link AssistantBuilderValidator.validate} when required fields are
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
 */
class AssistantBuilderValidator {

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
        const draft = assistantBuilderStore.draft;
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
        const draft = assistantBuilderStore.draft;
        const remix = assistantBuilderStore.mode === 'remix';
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

export const validator = new AssistantBuilderValidator();
