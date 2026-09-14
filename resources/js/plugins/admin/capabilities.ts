import type { AdminRow } from './schemas/admin-content.js';

/**
 * Model capabilities that can be switched from the models list without opening
 * the editor. Each one lives in a different field of the model row: a boolean
 * in `settings`, a modality in `input`/`output` or an entry of the
 * `native_capabilities` tag list.
 */
export type ModelCapabilityId =
    'file_upload' | 'vision' | 'tool_calling' | 'web_search' | 'code_execution' | 'image_generation';

interface ModelCapabilityDefinition {
    id: ModelCapabilityId;
    field: 'settings' | 'input' | 'output' | 'native_capabilities';
    key: string;
}

const definitions: ModelCapabilityDefinition[] = [
    { id: 'file_upload', field: 'settings', key: 'file_upload' },
    { id: 'vision', field: 'input', key: 'image' },
    { id: 'tool_calling', field: 'settings', key: 'tool_calling' },
    { id: 'web_search', field: 'native_capabilities', key: 'web_search' },
    { id: 'code_execution', field: 'native_capabilities', key: 'code_execution' },
    { id: 'image_generation', field: 'output', key: 'image' }
];

/** Display order of the quick toggles. */
export const modelCapabilities: ModelCapabilityId[] = definitions.map((definition) => definition.id);

function definition(id: ModelCapabilityId): ModelCapabilityDefinition {
    return definitions.find((item) => item.id === id)!;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

export function hasModelCapability(row: AdminRow, id: ModelCapabilityId): boolean {
    const { field, key } = definition(id);
    const value = row[field];
    if (field === 'settings') return isRecord(value) && value[key] === true;
    return Array.isArray(value) && value.includes(key);
}

/**
 * The field changes that switch one capability on or off. Everything else in
 * the touched field stays as it is, so the result can be merged into the row
 * and saved like an editor submission.
 */
export function toggleModelCapability(row: AdminRow, id: ModelCapabilityId, enabled: boolean): Record<string, unknown> {
    const { field, key } = definition(id);
    const value = row[field];
    if (field === 'settings') return { settings: { ...(isRecord(value) ? value : {}), [key]: enabled } };
    return { [field]: toggleTag(value, key, enabled) };
}

function toggleTag(list: unknown, key: string, enabled: boolean): unknown[] {
    const rest = (Array.isArray(list) ? list : []).filter((item) => item !== key);
    return enabled ? [...rest, key] : rest;
}

/**
 * A model is visible to users when its usage rules allow the main application;
 * models restricted to external applications stay hidden from the model picker.
 */
export function isModelVisible(row: AdminRow): boolean {
    return Array.isArray(row.usage_rules) && row.usage_rules.includes('main');
}

export function toggleModelVisible(row: AdminRow, enabled: boolean): Record<string, unknown> {
    return { usage_rules: toggleTag(row.usage_rules, 'main', enabled) };
}
