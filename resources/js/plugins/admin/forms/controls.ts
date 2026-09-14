import type { AdminField, AdminRow } from '../schemas/admin-content.js';
import type { SectionId } from '../sections.js';

export interface Control {
    type:
        | 'provider-icon'
        | 'text'
        | 'secret'
        | 'number'
        | 'boolean'
        | 'select'
        | 'multi'
        | 'list'
        | 'object'
        | 'pricing'
        | 'textarea'
        | 'localized-text'
        | 'datetime'
        | 'url'
        | 'markdown-locales';
    label?: string;
    options?: Array<{ value: string | number; label: string }>;
    /** Free-text completions offered next to a text control; the typed value stays authoritative. */
    suggestions?: Array<{ value: string; label: string }>;
    fields?: Record<string, Control>;
    item?: Control;
    custom?: boolean;
    optional?: boolean;
    min?: number;
    max?: number;
    step?: number | 'any';
    rows?: number;
    disabled?: boolean;
    hint?: string;
}
const number = (min = 0, max?: number, step: number | 'any' = 1): Control => ({
    type: 'number',
    optional: true,
    min,
    max,
    step
});
const text: Control = { type: 'text', optional: true };
const boolean: Control = { type: 'boolean', optional: true };
export const choices = (values: readonly string[]) => values.map((value) => ({ value, label: value }));
export const modalities = ['text', 'image', 'audio', 'video'];
export const capabilities = ['web_search', 'web_fetch', 'knowledge_base', 'code_execution', 'tool_calling'];
export const flags = [
    'open-weights',
    'eco-friendly',
    'self-hosted',
    'multi-modal',
    'strength-creative-writing',
    'strength-code-generation',
    'strength-math',
    'strength-role-playing',
    'strength-reasoning',
    'feature-streaming',
    'feature-sampling-parameters',
    'feature-response-schema',
    'feature-prompt-caching',
    'feature-reasoning-none',
    'feature-reasoning-minimal',
    'feature-reasoning-low',
    'feature-reasoning-medium',
    'feature-reasoning-high',
    'feature-reasoning-xhigh',
    'feature-reasoning-max'
];
export const parameters: Control = {
    type: 'object',
    custom: true,
    fields: {
        temperature: number(0, 2, 'any'),
        top_p: number(0, 1, 'any'),
        max_tokens: number(1),
        max_thinking_tokens: number(0)
    }
};
const modelSettings: Control = {
    type: 'object',
    custom: true,
    fields: {
        max_tool_calling_rounds: number(),
        max_tool_calling_rounds_streaming: number(),
        file_upload: boolean,
        tool_calling: boolean,
        native_capabilities: boolean
    }
};
const timeouts: Control = {
    type: 'object',
    fields: { read: number(0.1, 120, 'any'), connect: number(0.1, 120, 'any'), sse_idle: number(0.1, 120, 'any') }
};
const mimeTypes = [
    'text/plain',
    'text/markdown',
    'text/csv',
    'application/pdf',
    'application/json',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'audio/mpeg',
    'audio/wav',
    'video/mp4'
];

export function isFieldVisible(section: SectionId, field: AdminField, values: Record<string, unknown>): boolean {
    if (section !== 'providers') return true;
    // No built-in adapter reads model_status_url; status checks use model discovery.
    if (field.key === 'model_status_url') return false;
    if (field.key === 'api_url')
        return ['openai_like', 'openai_azure', 'ollama', 'huggingface', 'gwdg'].includes(String(values.adapter_key));
    return true;
}

export function controlFor(
    section: SectionId,
    field: AdminField,
    values: Record<string, unknown>,
    row: AdminRow | null
): Control {
    const key = field.key;
    if (section === 'models') {
        if (key === 'descriptions') return { type: 'localized-text' };
        if (['input', 'output'].includes(key)) return { type: 'multi', options: choices(modalities) };
        if (key === 'native_capabilities') return { type: 'multi', options: choices(capabilities), custom: true };
        if (key === 'flags') return { type: 'multi', options: choices(flags), custom: true };
        if (key === 'parameters') return parameters;
        if (key === 'settings') return modelSettings;
        if (key === 'limits')
            return { type: 'object', fields: { max_input_tokens: number(1), max_output_tokens: number(1) } };
        if (key === 'pricing') return { type: 'pricing' };
    }
    if (section === 'system-models' && key === 'prompts') {
        const translation = values.model_type === 'translation';
        return {
            type: 'localized-text',
            rows: 12,
            disabled: translation,
            hint: translation ? 'admin.system_prompt_not_used' : undefined
        };
    }
    if (section === 'providers') {
        if (key === 'settings')
            return {
                type: 'object',
                custom: true,
                fields: {
                    model_parameters: parameters,
                    adapter: {
                        type: 'object',
                        custom: true,
                        fields:
                            values.adapter_key === 'aws_bedrock' ?
                                {
                                    region: text,
                                    version: text,
                                    session_token: { type: 'secret', optional: true },
                                    use_default_credential_provider: boolean,
                                    assume_role: {
                                        type: 'object',
                                        fields: {
                                            arn: text,
                                            session_name: text,
                                            duration_seconds: number(900, 43200),
                                            external_id: text
                                        }
                                    }
                                }
                            : values.adapter_key === 'huggingface' ? { inference_provider: text }
                            : {}
                    }
                }
            };
        if (key === 'additional_config')
            return {
                type: 'object',
                custom: true,
                hint: 'admin.form.replace_secret',
                fields: values.adapter_key === 'openai_azure' ? { version: text } : {}
            };
    }
    if (section === 'mcp') {
        if (key === 'timeouts') return timeouts;
        if (key === 'additional_config')
            return {
                type: 'object',
                hint: 'admin.form.replace_secret',
                fields:
                    values.type === 'stdio' ?
                        {
                            args: { type: 'list', item: { type: 'text' } },
                            env: { type: 'object', custom: true, item: { type: 'secret' } }
                        }
                    :   {
                            headers: { type: 'object', custom: true, item: { type: 'secret' } },
                            http_options: {
                                type: 'object',
                                custom: true,
                                fields: {
                                    connectionTimeout: number(0.1, undefined, 'any'),
                                    readTimeout: number(0.1, undefined, 'any'),
                                    sseIdleTimeout: number(0.1, undefined, 'any'),
                                    verifyTls: boolean,
                                    enableSse: boolean,
                                    autoSse: boolean,
                                    maxRetries: number(),
                                    retryDelay: number(0, undefined, 'any'),
                                    sseDefaultRetryDelay: number(0, undefined, 'any'),
                                    sseReconnectBudget: number(0, undefined, 'any'),
                                    caFile: text,
                                    curlOptions: { type: 'object', custom: true }
                                }
                            }
                        }
            };
    }
    if (section === 'settings' && key === 'value') {
        const setting = String(row?.key ?? row?.id);
        if (setting.startsWith('ALLOWED_'))
            return {
                type: 'multi',
                custom: true,
                options: choices(
                    setting === 'ALLOWED_AVATAR_MIME_TYPES' ?
                        mimeTypes.filter((type) => type.startsWith('image/'))
                    :   mimeTypes
                )
            };
        const limits: Record<string, [number, number]> = {
            SESSION_LIFETIME: [1, 525600],
            ALLOW_EXTERNAL_APPS_CONNECT_REQUEST_TIMEOUT: [60, 86400],
            MAX_FILE_SIZE: [1, 1073741824],
            MAX_AVATAR_FILE_SIZE: [1, 52428800],
            MAX_ATTACHMENT_FILES: [0, 100],
            REMOVE_FILES_AFTER_MONTHS: [1, 120]
        };
        if (limits[setting]) return { ...number(...limits[setting]), optional: false };
        if (['ACCESSIBILITY_STATEMENT_URL', 'APP_URL'].includes(setting)) return { type: 'url' };
    }
    if (field.type === 'json' || field.type === 'secret-json')
        throw new Error(`Missing structured control for ${section}.${key}`);
    return { type: field.type as Control['type'], options: field.options };
}

export function record(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value) ?
            (value as Record<string, unknown>)
        :   {};
}

export function inferControl(value: unknown): Control {
    if (Array.isArray(value)) return { type: 'list', custom: true };
    if (value !== null && typeof value === 'object') return { type: 'object', custom: true };
    if (typeof value === 'number') return { type: 'number', step: 'any' };
    if (typeof value === 'boolean') return { type: 'boolean' };
    return { type: 'text' };
}

/** PHP represents an empty map as []; normalize only controls known to hold objects. */
export function normalizeControlValue(control: Control, value: unknown): unknown {
    if (control.type !== 'object') return value;
    if (Array.isArray(value) && value.length === 0) return {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) return value;
    const result = { ...record(value) };
    for (const [key, child] of Object.entries(control.fields ?? {})) {
        if (Object.hasOwn(result, key)) result[key] = normalizeControlValue(child, result[key]);
    }
    return result;
}
