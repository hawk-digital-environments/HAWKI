import type { AdminField, AdminRow } from './schemas/admin-content.js';
import { ApiTransportError } from '$lib/kernel/api/errors.js';

function cloneValue(value: unknown): unknown {
    if (Array.isArray(value)) return value.map(cloneValue);
    if (value !== null && typeof value === 'object')
        return Object.fromEntries(Object.entries(value).map(([key, child]) => [key, cloneValue(child)]));
    return value;
}

export function createDraft(fields: AdminField[], row: AdminRow | null): Record<string, unknown> {
    return Object.fromEntries(
        fields.map((field) => {
            const list =
                field.type === 'multi' ||
                (field.key === 'value' && String(row?.key ?? row?.id).startsWith('ALLOWED_')) ||
                ['input', 'output', 'native_capabilities', 'flags'].includes(field.key);
            const structured = field.type === 'json' || field.type === 'secret-json';
            const localized = ['localized-text', 'markdown-locales'].includes(field.type);
            let value =
                row?.[field.key] ??
                field.default ??
                (field.type === 'provider-icon' ? null
                : field.type === 'boolean' ? false
                : list ? []
                : structured || localized ? {}
                : '');
            if (field.type === 'secret') value = '';
            else if (field.type === 'secret-json') value = {};
            else if ((structured || localized) && !list && Array.isArray(value) && value.length === 0) value = {};
            else if (field.type === 'datetime' && value) {
                const date = new Date(
                    String(value).replace(' ', 'T') + (/Z$|[+-]\d\d:\d\d$/.test(String(value)) ? '' : 'Z')
                );
                value =
                    Number.isNaN(date.getTime()) ? '' : (
                        new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
                    );
            }
            return [field.key, structured || list || localized ? cloneValue(value) : value];
        })
    );
}

/** Keep typed structured values intact; blank secrets mean no replacement. */
export function prepareValues(
    fields: AdminField[],
    draft: Record<string, unknown>
): { values: Record<string, unknown>; errors: Record<string, string> } {
    const values: Record<string, unknown> = {};
    const errors: Record<string, string> = {};
    for (const field of fields) {
        const raw = draft[field.key];
        if (
            field.type.startsWith('secret') &&
            (raw === '' || raw == null || (typeof raw === 'object' && Object.keys(raw).length === 0))
        )
            continue;
        if (['json', 'secret-json'].includes(field.type) && (raw === null || typeof raw !== 'object')) {
            errors[field.key] = 'admin.errors.invalid_value';
        } else if (field.type === 'number' && (typeof raw !== 'number' || !Number.isFinite(raw))) {
            errors[field.key] = 'admin.errors.invalid_value';
        } else if (field.type === 'datetime') {
            const timestamp = raw ? Date.parse(String(raw)) : null;
            if (timestamp !== null && !Number.isFinite(timestamp)) errors[field.key] = 'admin.errors.invalid_value';
            else values[field.key] = timestamp === null ? null : new Date(timestamp).toISOString();
        } else values[field.key] = raw === '' && !field.required ? null : raw;
    }
    return { values, errors };
}

export function serverFieldErrors(error: unknown): Record<string, string> {
    if (!(error instanceof ApiTransportError)) return {};
    const body = error.body as { errors?: Array<{ source?: { pointer?: string }; detail?: string }> };
    const result: Record<string, string> = {};
    for (const item of body?.errors ?? []) {
        const pointer = item.source?.pointer?.replace(/^\/(?:data\/attributes\/|values\/)?/, '');
        if (!pointer || !item.detail) continue;
        const key = pointer.split(/[/.]/)[0];
        // The backend validates the domain type as `type`; the editor field is `kind`.
        result[key === 'type' ? 'kind' : key] = item.detail;
    }
    return result;
}

/** Display name of a row for dialogs and menu labels. */
export function rowName(row: AdminRow): string {
    return String(row.name ?? row.title ?? row.label ?? row.key ?? row.id);
}
