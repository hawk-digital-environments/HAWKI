import type { Assistant } from "$plugins/assistants/types/assistant/Assistant";
import { ValidationState } from "$plugins/assistants/types/enums/ValidationState";

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
export interface CompletenessRule {
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
export interface TriggerRule {
    id: string;
    group: string;
    keys: (keyof Assistant)[];
    changedLabel: string;
    unchangedLabel: string;
    description?: string;
}

export const COMPLETENESS_RULES: CompletenessRule[] = [
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

export const TRIGGER_RULES: TriggerRule[] = [
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
