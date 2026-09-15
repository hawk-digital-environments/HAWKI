import { readFileSync } from 'node:fs';
import { settingsTabs } from '../../../resources/js/plugins/admin/settings.js';
import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { FormApi, FieldApi, revalidateLogic } from '@tanstack/form-core';
import { createDraft, prepareValues } from '../../../resources/js/plugins/admin/form.js';
import { AdminFieldSchema } from '../../../resources/js/plugins/admin/schemas/admin-content.js';
import {
    editorSchema,
    formValidationSchema,
    parametersSchema,
    pricingSchema,
    mcpSchema,
    settingsSchemas,
    modelsSchema,
    providersSchema
} from '../../../resources/js/plugins/admin/forms/schemas.js';
import { controlFor, normalizeControlValue } from '../../../resources/js/plugins/admin/forms/controls.js';

const field = (key: string, type = 'text') => AdminFieldSchema.parse({ key, type });
const fields = [
    field('api_key', 'secret'),
    field('additional_config', 'secret-json'),
    field('settings', 'json'),
    field('limit', 'number')
];

test('editing never prepopulates or clears credentials implicitly', () => {
    const draft = createDraft(fields, {
        id: '1',
        api_key: 'must-not-appear',
        additional_config: { password: 'must-not-appear' },
        settings: { temperature: 0.7 },
        limit: 42
    });
    assert.equal(draft.api_key, '');
    assert.deepEqual(draft.additional_config, {});
    assert.deepEqual(prepareValues(fields, draft), {
        values: { settings: { temperature: 0.7 }, limit: 42 },
        errors: {}
    });
});

test('local user creation requires a matching twelve-character password', () => {
    const userFields = [
        field('name'),
        field('username'),
        field('email'),
        field('employeetype'),
        field('password', 'secret'),
        field('password_confirmation', 'secret'),
        field('admin_disabled', 'boolean'),
        field('roles', 'multi')
    ];
    const values = {
        name: 'Local User',
        username: 'local-user',
        email: 'local@example.test',
        employeetype: 'guest',
        password: 'long-enough-password',
        password_confirmation: 'long-enough-password',
        admin_disabled: false,
        roles: []
    };
    const createSchema = editorSchema('users', userFields, null);
    assert.equal(createSchema.safeParse(values).success, true);
    assert.equal(createSchema.safeParse({ ...values, password: '', password_confirmation: '' }).success, false);
    assert.equal(createSchema.safeParse({ ...values, password_confirmation: 'different-password' }).success, false);

    const editSchema = editorSchema('users', userFields, { id: '2', username: 'local-user' });
    assert.equal(editSchema.safeParse({ ...values, password: '', password_confirmation: '' }).success, true);
});

test('structured values remain typed and raw JSON strings are rejected', () => {
    const result = prepareValues(fields, {
        api_key: 'new-key',
        additional_config: { version: '2024-10-21' },
        settings: 'false',
        limit: 'invalid'
    });
    assert.deepEqual(result.values, { api_key: 'new-key', additional_config: { version: '2024-10-21' } });
    assert.deepEqual(Object.keys(result.errors), ['settings', 'limit']);
});

test('draft edits do not mutate rows and preserve extension parameters', () => {
    const row = { id: '1', parameters: { temperature: 0.7, custom: { enabled: true } }, flags: ['plugin-flag'] };
    const draft = createDraft([field('parameters', 'json'), field('flags', 'json')], row);
    const parsed = parametersSchema.parse(draft.parameters);
    assert.deepEqual(parsed.custom, { enabled: true });
    parsed.temperature = 1;
    assert.equal(row.parameters.temperature, 0.7);
    assert.deepEqual(draft.flags, ['plugin-flag']);
});

test('empty PHP maps normalize recursively without changing array fields', () => {
    const settings = controlFor('providers', field('settings', 'json'), { adapter_key: 'aws_bedrock' }, null);
    assert.deepEqual(normalizeControlValue(settings, { model_parameters: [], adapter: [], extension: [] }), {
        model_parameters: {},
        adapter: {},
        extension: []
    });
    assert.deepEqual(createDraft([field('input', 'json')], { id: '1', input: [] }).input, []);
    assert.deepEqual(createDraft([field('value', 'json')], { id: 'ALLOWED_FILE_MIME_TYPES', value: [] }).value, []);
});

test('known model parameters validate numeric boundaries and integer budgets', () => {
    assert.equal(
        parametersSchema.safeParse({ temperature: 2, top_p: 1, max_tokens: 1, max_thinking_tokens: 0 }).success,
        true
    );
    for (const invalid of [
        { temperature: 2.1 },
        { top_p: -0.1 },
        { max_tokens: 0 },
        { max_tokens: 1.5 },
        { max_thinking_tokens: -1 },
        { temperature: '1' }
    ])
        assert.equal(parametersSchema.safeParse(invalid).success, false);
});

test('model descriptions use a localized text control and validate each locale', () => {
    const descriptions = field('descriptions', 'localized-text');
    const control = controlFor('models', descriptions, {}, null);
    const schema = editorSchema('models', [descriptions], null);

    assert.equal(control.type, 'localized-text');
    assert.deepEqual(createDraft([descriptions], null), { descriptions: {} });
    assert.equal(schema.safeParse({ descriptions: { en_US: 'English', de_DE: 'Deutsch' } }).success, true);
    assert.equal(schema.safeParse({ descriptions: { en_US: 'x'.repeat(30001) } }).success, false);
    assert.equal(schema.safeParse({ descriptions: { fr_FR: 'Français' } }).success, false);
});

test('system prompts live in the system model form except for translation models', () => {
    const prompts = field('prompts', 'localized-text');
    const schema = editorSchema('system-models', [prompts], null);
    const summaryControl = controlFor('system-models', prompts, { model_type: 'summary' }, null);
    const translationControl = controlFor('system-models', prompts, { model_type: 'translation' }, null);

    assert.equal(summaryControl.type, 'localized-text');
    assert.equal(summaryControl.rows, 12);
    assert.equal(summaryControl.disabled, false);
    assert.equal(translationControl.disabled, true);
    assert.equal(translationControl.hint, 'admin.system_prompt_not_used');
    assert.deepEqual(createDraft([prompts], null), { prompts: {} });
    assert.equal(
        schema.safeParse({ prompts: { en_US: 'Summarize this chat.', de_DE: 'Fasse den Chat zusammen.' } }).success,
        true
    );
    assert.equal(schema.safeParse({ prompts: { en_US: 'x'.repeat(100001) } }).success, false);
    assert.equal(schema.safeParse({ prompts: { fr_FR: 'Résume cette discussion.' } }).success, false);
});

test('pricing preserves unknown, free and partial pricing states', () => {
    for (const value of [
        {},
        { ranges: [], priority_ranges: [] },
        { ranges: [{ currency: 'EUR', input_cost_per_token: 0.000001, range: [0, null] }], priority_ranges: null }
    ])
        assert.deepEqual(pricingSchema.parse(value), value);
    assert.equal(pricingSchema.safeParse({ ranges: [{ currency: 'USD', range: [5, 5] }] }).success, false);
    assert.equal(
        pricingSchema.safeParse({
            ranges: [
                { currency: 'USD', range: [0, 10] },
                { currency: 'USD', range: [9, null] }
            ]
        }).success,
        false
    );
    assert.equal(
        pricingSchema.safeParse({
            ranges: [
                { currency: 'USD', range: [0, 10] },
                { currency: 'USD', range: [10, null] }
            ]
        }).success,
        true
    );
    assert.equal(
        pricingSchema.safeParse({ ranges: [{ currency: 'USD', range: [0, null], input_cost_per_token: -1 }] }).success,
        false
    );
});

test('MCP validates transport URLs and typed configuration', () => {
    const fields = Object.keys(mcpSchema.shape).map((key) => field(key));
    const schema = editorSchema('mcp', fields, null);
    const values = {
        server_label: 'Local',
        kind: 'stdio',
        url: '/usr/bin/server',
        require_approval: 'always',
        additional_config: { args: ['--verbose'], env: { TOKEN: 'test' } },
        timeouts: { read: 0.1, connect: 120 }
    };
    assert.equal(schema.safeParse(values).success, true);
    assert.equal(schema.safeParse({ ...values, kind: 'http' }).success, false);
    assert.equal(schema.safeParse({ ...values, kind: 'http', url: 'https://example.test/mcp' }).success, true);
    assert.equal(schema.safeParse({ ...values, timeouts: { read: 121 } }).success, false);
    assert.equal(schema.safeParse({ ...values, additional_config: { headers: { Authorization: 5 } } }).success, false);
});

test('setting schemas validate MIME syntax, integer ranges and empty allowlists', () => {
    const mime = settingsSchemas.ALLOWED_FILE_MIME_TYPES;
    for (const value of [[], ['image/*', 'application/vnd.api+json']])
        assert.equal(mime.safeParse(value).success, true);
    for (const value of ['image/png', ['invalid'], [42]]) assert.equal(mime.safeParse(value).success, false);
    assert.equal(settingsSchemas.MAX_ATTACHMENT_FILES.safeParse(0).success, true);
    assert.equal(settingsSchemas.MAX_ATTACHMENT_FILES.safeParse(101).success, false);
    assert.equal(settingsSchemas.MAX_FILE_SIZE.safeParse(1.5).success, false);
});

test('user forms only require fields permitted by the server and reject invalid choices', () => {
    const roles = AdminFieldSchema.parse({ key: 'roles', type: 'multi', options: [{ value: 3, label: 'Member' }] });
    const schema = editorSchema('users', [roles], { id: '10' });
    assert.deepEqual(schema.parse({ roles: [3] }), { roles: [3] });
    assert.equal(schema.safeParse({ roles: [4] }).success, false);
    assert.equal(schema.safeParse({ roles: [3, 3] }).success, false);
});

test('announcement schema rejects date order, empty publication and targeted policies', () => {
    const names = [
        'title',
        'kind',
        'is_published',
        'is_global',
        'is_forced',
        'target_roles',
        'starts_at',
        'expires_at',
        'anchor',
        'content'
    ];
    const schema = editorSchema(
        'announcements',
        names.map((key) => field(key)),
        null
    );
    const valid = {
        title: 'News',
        kind: 'news',
        is_published: true,
        is_global: false,
        is_forced: false,
        target_roles: [],
        content: { en_US: 'Hello' }
    };
    assert.equal(schema.safeParse(valid).success, true);
    for (const value of [
        { ...valid, content: { en_US: ' ' } },
        { ...valid, kind: 'policy' },
        { ...valid, starts_at: '2026-10-02', expires_at: '2026-10-01' }
    ])
        assert.equal(schema.safeParse(value).success, false);
    assert.doesNotThrow(() => schema.safeParse({ ...valid, title: '' }));
});

test('immutable provider keys are checked on update', () => {
    const key = AdminFieldSchema.parse({ key: 'provider_id', type: 'text', immutable: true });
    const schema = editorSchema('providers', [key], { id: '1', provider_id: 'original' });
    assert.equal(schema.safeParse({ provider_id: 'changed' }).success, false);
});

test('TanStack Form blocks invalid submissions using the dialog Zod schema', async () => {
    let submitted = 0;
    const schema = editorSchema('settings', [field('value', 'number')], { id: 'MAX_ATTACHMENT_FILES' });
    const form = new FormApi({
        defaultValues: { value: 101 },
        validationLogic: revalidateLogic({ mode: 'blur', modeAfterSubmission: 'change' }),
        validators: { onSubmit: schema, onDynamic: schema },
        onSubmit: () => {
            submitted++;
        }
    });
    const unmount = form.mount();
    const input = new FieldApi({ form, name: 'value' });
    const unmountField = input.mount();
    try {
        await form.handleSubmit();
        assert.equal(submitted, 0);
        assert.equal(form.state.isValid, false);
        input.handleChange(10);
        await form.handleSubmit();
        assert.equal(submitted, 1);
    } finally {
        unmountField();
        unmount();
    }
});

test('all formerly JSON configuration fields have structured controls', () => {
    for (const [section, schema] of [
        ['models', modelsSchema],
        ['providers', providersSchema],
        ['mcp', mcpSchema]
    ] as const) {
        const keys =
            section === 'models' ?
                ['input', 'output', 'parameters', 'native_capabilities', 'settings', 'limits', 'pricing', 'flags']
            : section === 'providers' ? ['settings', 'additional_config']
            : ['timeouts', 'additional_config'];
        for (const key of keys) {
            assert.ok(key in schema.shape);
            const control = controlFor(
                section,
                field(key, 'json'),
                { adapter_key: 'openai_azure', kind: 'http' },
                null
            );
            assert.ok(['object', 'multi', 'pricing'].includes(control.type));
        }
    }
});

test('MCP connection settings follow the transport kind of the draft', () => {
    const config = field('additional_config', 'json');
    const stdio = controlFor('mcp', config, { kind: 'stdio' }, null);
    const http = controlFor('mcp', config, { kind: 'http' }, null);
    assert.equal(stdio.type, 'object');
    assert.equal(http.type, 'object');
    assert.deepEqual(Object.keys(stdio.type === 'object' ? (stdio.fields ?? {}) : {}), ['args', 'env']);
    assert.deepEqual(Object.keys(http.type === 'object' ? (http.fields ?? {}) : {}), ['headers', 'http_options']);
});

test('draft cloning supports reactive row proxies', () => {
    const settings = new Proxy({ tool_calling: true }, {});
    const row = new Proxy({ id: '1', settings }, {});
    assert.deepEqual(createDraft([field('settings', 'json')], row), { settings: { tool_calling: true } });
});

test('nested errors on composite fields clear after correction and allow resubmission', async () => {
    let submitted = 0;
    const schema = formValidationSchema(editorSchema('providers', [field('settings', 'json')], null));
    const form = new FormApi({
        defaultValues: { settings: { model_parameters: { temperature: 0.7 } } },
        validationLogic: revalidateLogic({ mode: 'blur', modeAfterSubmission: 'change' }),
        validators: { onDynamic: schema, onSubmit: schema },
        onSubmit: () => {
            submitted++;
        }
    });
    const unmount = form.mount();
    const input = new FieldApi({ form, name: 'settings' });
    const unmountField = input.mount();
    try {
        input.handleChange({ model_parameters: { temperature: 3 } });
        await form.handleSubmit();
        assert.equal(submitted, 0);
        input.handleChange({ model_parameters: { temperature: 0.5 } });
        await form.handleSubmit();
        assert.equal(submitted, 1);
    } finally {
        unmountField();
        unmount();
    }
});

test('system settings validate application, authentication and session values', () => {
    assert.equal(settingsSchemas.APP_URL.safeParse('').success, false);
    assert.equal(settingsSchemas.APP_URL.safeParse('javascript:alert(1)').success, false);
    assert.equal(settingsSchemas.APP_URL.safeParse('https://hawki.example.com').success, true);
    assert.equal(settingsSchemas.APP_ENV.safeParse('staging').success, true);
    assert.equal(settingsSchemas.APP_ENV.safeParse('bad environment').success, false);
    assert.equal(settingsSchemas.AUTHENTICATION_METHOD.safeParse('ArbitraryClass').success, false);
    assert.equal(settingsSchemas.AUTHENTICATION_METHOD.safeParse('OIDC').success, true);
    assert.equal(settingsSchemas.SESSION_LIFETIME.safeParse(0).success, false);
    assert.equal(settingsSchemas.SESSION_LIFETIME.safeParse(5000).success, true);
    assert.equal(settingsSchemas.SESSION_ENCRYPT.safeParse('false').success, false);
    assert.equal(settingsSchemas.SESSION_ENCRYPT.safeParse(false).success, true);
    const locale = AdminFieldSchema.parse({
        key: 'value',
        type: 'select',
        options: [{ value: 'de_DE', label: 'Deutsch' }]
    });
    const schema = editorSchema('settings', [locale], { id: 'APP_LOCALE' });
    assert.equal(schema.safeParse({ value: 'de_DE' }).success, true);
    assert.equal(schema.safeParse({ value: 'en_US' }).success, false);
});

test('every editable backend setting has one tab and a client validator', () => {
    const backend = readFileSync(new URL('../../../app/Services/Admin/SystemSettings.php', import.meta.url), 'utf8');
    const definitions = backend.split('public const DEFINITIONS = [')[1].split('\n    ];')[0];
    const keys = [...definitions.matchAll(/^\s*'([A-Z0-9_]+)' =>/gm)].map((match) => match[1]);
    const grouped = settingsTabs.flatMap((tab) => tab.groups.flatMap((group) => group.settings));
    assert.equal(new Set(grouped).size, grouped.length);
    assert.deepEqual([...grouped].sort(), keys.sort());
    for (const key of keys) assert.ok(settingsSchemas[key], `Missing validator for ${key}`);
});
