import z from 'zod';
import type { AdminField, AdminRow } from '../schemas/admin-content.js';
import type { SectionId } from '../sections.js';

const text = (max = 255) => z.string().trim().min(1).max(max);
const optionalText = (max = 255) => z.string().max(max).nullish();
const url = z.union([z.literal(''), z.url({ protocol: /^https?$/ }).max(2000)]).nullish();
const date = z
    .string()
    .refine((value) => !value || Number.isFinite(Date.parse(value)))
    .nullish();
const id = z.number().int().positive();
const ids = z.array(id).refine((values) => new Set(values).size === values.length);
const tags = z.array(text()).refine((values) => new Set(values).size === values.length);
const object = <T extends z.ZodRawShape>(shape: T) => z.looseObject(shape);
const optionalNumber = (min = 0, max = Number.MAX_SAFE_INTEGER, integer = false) =>
    (integer ? z.number().int() : z.number()).min(min).max(max).nullish();

export const parametersSchema = object({
    temperature: optionalNumber(0, 2),
    top_p: optionalNumber(0, 1),
    max_tokens: optionalNumber(1, Number.MAX_SAFE_INTEGER, true),
    max_thinking_tokens: optionalNumber(0, Number.MAX_SAFE_INTEGER, true)
});
export const modelSettingsSchema = object({
    max_tool_calling_rounds: optionalNumber(0, Number.MAX_SAFE_INTEGER, true),
    max_tool_calling_rounds_streaming: optionalNumber(0, Number.MAX_SAFE_INTEGER, true),
    file_upload: z.boolean().nullish(),
    tool_calling: z.boolean().nullish(),
    native_capabilities: z.boolean().nullish()
});
export const limitsSchema = object({
    max_input_tokens: optionalNumber(1, Number.MAX_SAFE_INTEGER, true),
    max_output_tokens: optionalNumber(1, Number.MAX_SAFE_INTEGER, true)
});
const pricingRangeSchema = object({
    currency: z.enum(['USD', 'EUR']),
    input_cost_per_token: optionalNumber(),
    input_cost_per_cached_token: optionalNumber(),
    output_cost_per_token: optionalNumber(),
    output_cost_per_reasoning_token: optionalNumber(),
    range: z.tuple([z.number().int().min(0), z.number().int().positive().nullable()])
}).superRefine((value, ctx) => {
    if (value.range[1] !== null && value.range[1] <= value.range[0])
        ctx.addIssue({ code: 'custom', path: ['range'], message: 'admin.validation.range' });
});
const rangesSchema = z.array(pricingRangeSchema).superRefine((ranges, ctx) => {
    for (let i = 0; i < ranges.length; i++)
        for (let j = 0; j < i; j++) {
            if (
                ranges[i].range[0] < (ranges[j].range[1] ?? Infinity) &&
                ranges[j].range[0] < (ranges[i].range[1] ?? Infinity)
            )
                ctx.addIssue({ code: 'custom', path: [i, 'range'], message: 'admin.validation.overlap' });
        }
});
export const pricingSchema = object({ ranges: rangesSchema.nullish(), priority_ranges: rangesSchema.nullish() });
export const timeoutsSchema = object({
    read: optionalNumber(0.1, 120),
    connect: optionalNumber(0.1, 120),
    sse_idle: optionalNumber(0.1, 120)
});
const adapterSettingsSchema = object({
    region: optionalText(),
    version: optionalText(),
    inference_provider: optionalText(),
    session_token: optionalText(16000),
    use_default_credential_provider: z.boolean().nullish(),
    assume_role: object({
        arn: optionalText(2048),
        session_name: optionalText(),
        external_id: optionalText(1224),
        duration_seconds: optionalNumber(900, 43200, true)
    }).nullish()
});
const providerSettingsSchema = object({
    model_parameters: parametersSchema.nullish(),
    adapter: adapterSettingsSchema.nullish()
});
const secret = z.string().max(16000).optional();
const additionalConfigSchema = object({ version: optionalText() });
const httpOptionsSchema = object({
    connectionTimeout: optionalNumber(0.1),
    readTimeout: optionalNumber(0.1),
    sseIdleTimeout: optionalNumber(0.1),
    verifyTls: z.boolean().nullish(),
    enableSse: z.boolean().nullish(),
    autoSse: z.boolean().nullish(),
    maxRetries: optionalNumber(0, Number.MAX_SAFE_INTEGER, true),
    retryDelay: optionalNumber(),
    sseDefaultRetryDelay: optionalNumber(),
    sseReconnectBudget: optionalNumber(),
    caFile: optionalText(2000),
    curlOptions: z.record(z.string().regex(/^\d+$/), z.unknown()).optional()
});
export const mcpConfigSchema = object({
    args: z.array(z.string()).optional(),
    env: z.record(z.string(), z.string()).optional(),
    headers: z.record(z.string(), z.string()).optional(),
    http_options: httpOptionsSchema.optional()
});

export const providersSchema = z.object({
    name: text(),
    icon: z.record(z.string(), z.unknown()).nullable().optional(),
    provider_id: text(100).regex(/^[\p{L}\p{M}\p{N}_-]+$/u),
    adapter_key: text(),
    active: z.boolean(),
    api_url: url,
    model_status_url: url,
    api_key: secret,
    additional_config: additionalConfigSchema,
    settings: providerSettingsSchema
});
export const modelsSchema = z.object({
    label: text(),
    model_id: text(),
    provider_id: id,
    descriptions: z.strictObject({ en_US: optionalText(30000), de_DE: optionalText(30000) }),
    active: z.boolean(),
    model_type: text(),
    documentation_url: url,
    deprecation_date: date,
    input: z.array(z.enum(['text', 'image', 'audio', 'video'])),
    output: z.array(z.enum(['text', 'image', 'audio', 'video'])),
    parameters: parametersSchema,
    native_capabilities: tags,
    settings: modelSettingsSchema,
    limits: limitsSchema,
    pricing: pricingSchema,
    flags: tags,
    tools: ids,
    usage_rules: z.array(z.enum(['main', 'external']))
});
export const mcpSchema = z.object({
    server_label: text(),
    kind: z.enum(['http', 'sse', 'stdio']),
    url: text(2000),
    description: optionalText(10000),
    require_approval: z.enum(['never', 'always']),
    api_key: secret,
    additional_config: mcpConfigSchema,
    timeouts: timeoutsSchema
});
export const toolsSchema = z.object({
    description: optionalText(10000),
    active: z.boolean(),
    mapped_capability: optionalText(),
    models: ids
});
const promptSlot = z.enum(['default', 'title_generation', 'prompt_improvement', 'summary']);
const modelSlot = z.enum([...promptSlot.options, 'translation']);
const usage = z.enum(['main', 'external']);
export const systemModelsSchema = z.object({
    model_type: modelSlot,
    usage_type: usage,
    model_id: text(),
    prompts: z.strictObject({ en_US: optionalText(100000), de_DE: optionalText(100000) })
});
export const announcementsSchema = z.object({
    title: text(),
    kind: z.enum(['news', 'system', 'event', 'info', 'policy']),
    is_published: z.boolean(),
    is_global: z.boolean(),
    is_forced: z.boolean(),
    target_roles: ids,
    starts_at: date,
    expires_at: date,
    anchor: optionalText(),
    content: z.object({ en_US: z.string().max(200000).optional(), de_DE: z.string().max(200000).optional() })
});
export const usersSchema = z.object({
    name: text(),
    username: text(),
    email: z.email().max(255),
    employeetype: text(),
    password: z.string().max(255).optional(),
    password_confirmation: z.string().max(255).optional(),
    admin_disabled: z.boolean(),
    roles: ids
});
export const rolesSchema = z.object({
    name: text(),
    slug: text(80).regex(/^[\p{L}\p{M}\p{N}_-]+$/u),
    description: optionalText(2000),
    permissions: tags
});
export const mappingsSchema = z.object({ employee_type: text(), role_id: id });
const mimeList = z.array(
    z
        .string()
        .max(100)
        .regex(/^[a-z0-9.+-]+\/[a-z0-9.+*-]+$/)
);
export const settingsSchemas: Record<string, z.ZodType> = {
    APP_NAME: text(),
    APP_URL: url.refine((value) => !!value, 'admin.validation.required'),
    APP_ENV: text(50).regex(/^[a-zA-Z0-9_-]+$/),
    APP_TIMEZONE: text(),
    APP_LOCALE: text(),
    AUTHENTICATION_METHOD: z.enum(['LDAP', 'OIDC', 'Shibboleth']),
    SESSION_LIFETIME: z.number().int().min(1).max(525600),
    AI_MENTION_HANDLE: text(50).regex(/^[\p{L}\p{M}\p{N}_-]+$/u),
    ACCESSIBILITY_STATEMENT_URL: url,
    ALLOW_EXTERNAL_APPS_CONNECT_REQUEST_TIMEOUT: z.number().int().min(60).max(86400),
    MAX_FILE_SIZE: z.number().int().min(1).max(1073741824),
    MAX_AVATAR_FILE_SIZE: z.number().int().min(1).max(52428800),
    MAX_ATTACHMENT_FILES: z.number().int().min(0).max(100),
    REMOVE_FILES_AFTER_MONTHS: z.number().int().min(1).max(120),
    ALLOWED_FILE_MIME_TYPES: mimeList,
    ALLOWED_AVATAR_MIME_TYPES: mimeList,
    ...Object.fromEntries(
        [
            'APP_SECURITY_PASSKEY_ALLOW_PASTE',
            'APP_SECURITY_PASSKEY_AUTO_GENERATE',
            'APP_SECURITY_PASSKEY_CHAR_LIMITATION',
            'SESSION_EXPIRE_ON_CLOSE',
            'SESSION_ENCRYPT',
            'ALLOW_EXTERNAL_COMMUNICATION',
            'ALLOW_USER_TOKEN_CREATION',
            'ALLOW_EXTERNAL_APPS',
            'ALLOW_EXTERNAL_APPS_GROUPS_AI',
            'CHECK_TOOL_STATUS'
        ].map((key) => [key, z.boolean()])
    )
};
const sectionSchemas = {
    'providers': providersSchema,
    'models': modelsSchema,
    'mcp': mcpSchema,
    'tools': toolsSchema,
    'system-models': systemModelsSchema,
    'announcements': announcementsSchema,
    'users': usersSchema,
    'roles': rolesSchema,
    'mappings': mappingsSchema
};

/** A distinct section schema, restricted to the fields the server permits this actor to edit. */
export function editorSchema(section: SectionId, fields: AdminField[], row: AdminRow | null) {
    const schema =
        section === 'settings' ?
            z.object({ value: settingsSchemas[String(row?.key ?? row?.id)] ?? z.never() })
        :   sectionSchemas[section as keyof typeof sectionSchemas];
    if (!schema) throw new Error(`No editor schema for ${section}`);
    const shape: Record<string, z.ZodType> = {};
    for (const field of fields) {
        const fieldSchema = (schema.shape as Record<string, z.ZodType>)[field.key];
        if (!fieldSchema) throw new Error(`No editor schema for ${section}.${field.key}`);
        shape[field.key] = fieldSchema;
    }
    return z.object(shape).superRefine((values, ctx) => {
        for (const field of fields) {
            const value = values[field.key];
            if (field.options.length && ['select', 'multi'].includes(field.type)) {
                const selected = Array.isArray(value) ? value : [value];
                if (selected.some((item) => !field.options.some((option) => option.value === item)))
                    ctx.addIssue({ code: 'custom', path: [field.key], message: 'admin.validation.option' });
            }
            if (row && field.immutable && value !== row[field.key])
                ctx.addIssue({ code: 'custom', path: [field.key], message: 'admin.errors.immutable' });
        }
        if (section === 'mcp' && values.kind !== 'stdio' && !url.safeParse(values.url).success)
            ctx.addIssue({ code: 'custom', path: ['url'], message: 'admin.validation.url' });
        if (section === 'announcements') {
            const parsed = announcementsSchema.safeParse(values);
            if (!parsed.success) return;
            const announcement = parsed.data;
            if (
                announcement.starts_at &&
                announcement.expires_at &&
                Date.parse(announcement.expires_at) < Date.parse(announcement.starts_at)
            )
                ctx.addIssue({ code: 'custom', path: ['expires_at'], message: 'admin.validation.dates' });
            if (announcement.is_published && !Object.values(announcement.content).some((value) => value?.trim()))
                ctx.addIssue({ code: 'custom', path: ['content'], message: 'admin.errors.content_required' });
            if (announcement.kind === 'policy' && (!announcement.is_global || announcement.target_roles.length))
                ctx.addIssue({ code: 'custom', path: ['is_global'], message: 'admin.errors.global_policy' });
            if (
                row?.kind === 'policy' &&
                row.is_published &&
                (announcement.kind !== 'policy' || !announcement.is_published)
            )
                ctx.addIssue({ code: 'custom', path: ['kind'], message: 'admin.errors.published_policy' });
        }
        if (section === 'users' && fields.some((field) => field.key === 'password')) {
            const password = typeof values.password === 'string' ? values.password : '';
            const confirmation = typeof values.password_confirmation === 'string' ? values.password_confirmation : '';
            if (!row && !password)
                ctx.addIssue({ code: 'custom', path: ['password'], message: 'admin.validation.required' });
            if (password && password.length < 12)
                ctx.addIssue({ code: 'custom', path: ['password'], message: 'admin.validation.password_length' });
            if (password !== confirmation)
                ctx.addIssue({
                    code: 'custom',
                    path: ['password_confirmation'],
                    message: 'admin.validation.password_confirmation'
                });
        }
    });
}

/** Composite controls register their root field; keep nested errors on that field so edits clear them. */
export function formValidationSchema(
    schema: z.ZodType<Record<string, unknown>>,
    formatIssue: (issue: z.core.$ZodIssue) => string = (issue) => issue.message
) {
    return z.record(z.string(), z.unknown()).superRefine((values, ctx) => {
        const result = schema.safeParse(values);
        if (result.success) return;
        for (const issue of result.error.issues)
            ctx.addIssue({
                code: 'custom',
                path: issue.path.slice(0, 1),
                message: formatIssue(issue)
            });
    });
}
