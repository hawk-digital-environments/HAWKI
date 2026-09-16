import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import type { JsonApiCollection } from '../../../resources/js/kernel/api/jsonApiEncoding.js';
import { RestApi } from '../../../resources/js/kernel/api/RestApi.js';
import { UriBuilder } from '../../../resources/js/kernel/api/UriBuilder.js';
import {
    ProviderDiscoverySchema,
    type ProviderDiscovery
} from '../../../resources/js/plugins/admin/schemas/admin-actions.js';
import { AdminRowSchema, type AdminRow } from '../../../resources/js/plugins/admin/schemas/admin-content.js';
import { sections, type SectionId } from '../../../resources/js/plugins/admin/sections.js';
import { AdminWorkspace, type AdminWorkspaceOptions } from '../../../resources/js/plugins/admin/workspace.svelte.js';

function fixture<Results extends Record<string, unknown> = {}>(
    section: SectionId,
    response: unknown = { data: [], meta: {} },
    options: AdminWorkspaceOptions<AdminRow, Results> = {},
    actionResponse: unknown = {}
) {
    const calls: { url: string; options: RequestInit }[] = [];
    const uriBuilder = new UriBuilder('https://hawki.test');
    const restApi = new RestApi(
        uriBuilder,
        async (url, options) => {
            calls.push({ url, options });
            if (url.includes('/actions/')) return actionResponse;
            return options.method === 'GET' ? response : { id: '17' };
        },
        () => {
            throw new Error('Connection not loaded');
        },
        () => AdminRowSchema
    );
    const workspace = new AdminWorkspace<AdminRow, string, Results>(
        (label) => label,
        [{ id: 'kind' }],
        (signal, query) => restApi.getResourceCollection(`admin-${section}`, { query, signal }),
        options
    );
    return { workspace, calls, restApi };
}

test('workspace delegates saves, updates, removal and refresh without an application or section', async () => {
    const events: unknown[] = [];
    const { workspace } = fixture(
        'roles',
        {
            data: [{ type: 'admin-roles', id: '17', attributes: { name: 'Role' }, meta: { version: 'version' } }],
            meta: { fields: [{ key: 'name', type: 'text' }], create: true, delete: true }
        },
        {
            save: async (values, row) => {
                events.push(['save', values, row?.id ?? null, row?._version]);
            },
            remove: async (row) => {
                events.push(['remove', row.id, row._version]);
            },
            refresh: async () => {
                events.push('refresh');
            }
        }
    );
    await workspace.load();
    assert.equal(workspace.canCreate, true);
    assert.equal(workspace.canEdit, true);
    assert.equal(workspace.canDelete, true);
    await workspace.save({ name: 'Created' });
    const row = workspace.rows[0];
    workspace.edit(row, null);
    await workspace.save({ name: 'Edited' });
    await workspace.update(row, { name: 'Updated' });
    workspace.remove(row, null);
    assert.equal(events.length, 6, 'removal waits for confirmation');
    await workspace.confirm();
    assert.deepEqual(events, [
        ['save', { name: 'Created' }, null, undefined],
        'refresh',
        ['save', { name: 'Edited' }, '17', 'version'],
        'refresh',
        ['save', { name: 'Updated' }, '17', 'version'],
        'refresh',
        ['remove', '17', 'version'],
        'refresh'
    ]);
});

test('workspace reads send standard table queries and abort superseded requests', async () => {
    const { workspace, calls } = fixture('models');
    workspace.pagination = { pageIndex: 1, pageSize: 10 };
    workspace.sorting = [{ id: 'label', desc: true }];
    workspace.search = 'a & b';
    await workspace.load();
    const url = new URL(calls[0].url);
    assert.equal(url.searchParams.get('page[number]'), '2');
    assert.equal(url.searchParams.get('page[size]'), '10');
    assert.equal(url.searchParams.get('sort'), '-label');
    assert.equal(url.searchParams.get('filter[search]'), 'a & b');
    await workspace.load();
    assert.equal(calls[0].options.signal?.aborted, true);
    workspace.dispose();
    assert.equal(calls[1].options.signal?.aborted, true);
});

test('configured actions retain their result and row context after confirmation', async () => {
    const response = { models: [{ model_id: 'example', label: 'Example' }] };
    const { workspace, restApi } = fixture<{ discover: ProviderDiscovery }>(
        'providers',
        { data: [] },
        {
            rowActions: (row) => ({
                discover: {
                    confirm: true,
                    dialog: true,
                    run: () =>
                        restApi.postToResourceAction(
                            'admin-providers',
                            `${row.id}/actions/discover`,
                            {},
                            {
                                schema: ProviderDiscoverySchema
                            }
                        )
                }
            })
        },
        response
    );
    workspace.action(workspace.rowActions({ id: '17' })[0]);
    assert.deepEqual(workspace.results, {});
    await workspace.confirm();
    assert.deepEqual(workspace.results.discover, { id: '17', response, trigger: null });
    assert.equal(workspace.error, '');
    assert.equal(workspace.busy, false);
    workspace.closeResult('discover');
    assert.deepEqual(workspace.results, {});
});

test('server side actions refresh the dependent caches like writes do', async () => {
    const events: string[] = [];
    const { workspace, calls } = fixture(
        'models',
        { data: [] },
        {
            rowActions: () => ({ refresh: { confirm: true, run: async () => events.push('run') } }),
            refresh: async () => {
                events.push('refresh');
            }
        }
    );
    workspace.action(workspace.rowActions({ id: '17' })[0]);
    await workspace.confirm();
    assert.deepEqual(events, ['run', 'refresh']);
    assert.equal(workspace.notice, 'admin.action_done');
    assert.equal(calls.filter((call) => call.options.method === 'GET').length, 1, 'the table reloads after the caches');
});

test('every section decodes collection metadata and edit versions', async () => {
    for (const section of sections) {
        const { workspace, calls } = fixture(section.id, {
            data: [{ type: `admin-${section.id}`, id: '17', attributes: { name: 'Example' }, meta: { version: 'v1' } }],
            meta: {
                fields: [{ key: 'name', type: 'text' }],
                create: true,
                page: { currentPage: 2, perPage: 1, total: 3 }
            }
        });
        await workspace.load();
        assert.equal(new URL(calls[0].url).pathname, `/api/hawki/v1/admin-${section.id}`);
        assert.equal(workspace.rows[0].id, '17');
        assert.equal(workspace.rows[0].name, 'Example');
        assert.equal(workspace.rows[0]._version, 'v1');
        assert.equal(workspace.total, 3);
        assert.equal(workspace.content?.page, 2);
        assert.equal(workspace.content?.size, 1);
        assert.equal(workspace.fields[0].key, 'name');
        assert.equal(workspace.canCreate, false, 'server metadata cannot enable an absent save operation');
    }
});

test('invalid collection responses leave a visible load error', async () => {
    for (const response of [
        { content: { rows: [] } },
        { data: [{ type: 'admin-models', attributes: {} }] },
        { data: [], meta: { fields: 'invalid' } }
    ]) {
        const { workspace } = fixture('models', response);
        await workspace.load();
        assert.notEqual(workspace.error, '');
        assert.equal(workspace.content, null);
        assert.equal(workspace.loading, false);
    }
});

test('domain types keep their kind attribute and are never confused with the resource type', async () => {
    const { workspace, calls } = fixture('announcements', {
        data: [{ type: 'admin-announcements', id: '17', attributes: { kind: 'news' } }]
    });
    workspace.sorting = [{ id: 'kind', desc: true }];
    await workspace.load();
    assert.equal(workspace.rows[0].kind, 'news');
    assert.equal(workspace.rows[0].type, undefined);
    assert.equal(new URL(calls[0].url).searchParams.get('sort'), '-kind');
});

test('read-only workspaces cannot edit, create, remove or reset records', async () => {
    const { workspace } = fixture(
        'environment',
        {
            data: [],
            meta: { create: true, delete: true, fields: [{ key: 'name', type: 'text' }] }
        },
        { resettable: true }
    );
    await workspace.load();
    assert.equal(workspace.canCreate, false);
    assert.equal(workspace.canEdit, false);
    assert.equal(workspace.canDelete, false);
    assert.equal(workspace.resettable, false);
    workspace.remove({ id: '17' }, null);
    assert.equal(workspace.confirmation, null);
    await assert.rejects(workspace.save({}), /admin.errors.save/);
});

test('failed mutations release row locks and do not refresh', async () => {
    let refreshed = false;
    const { workspace } = fixture(
        'roles',
        { data: [] },
        {
            save: async () => {
                throw new Error('Conflict');
            },
            refresh: async () => {
                refreshed = true;
            }
        }
    );
    const row: AdminRow = { id: '17' };
    await workspace.update(row, {});
    assert.equal(workspace.error, 'Conflict');
    assert.equal(workspace.locked(row), false);
    assert.equal(refreshed, false);
});

test('a successful save still reloads when the supplied cache refresh fails', async () => {
    const { workspace, calls } = fixture(
        'roles',
        { data: [] },
        {
            save: async () => {},
            refresh: async () => {
                throw new Error('Cache unavailable');
            }
        }
    );
    await workspace.save({});
    assert.equal(workspace.notice, 'admin.saved_refresh');
    assert.equal(workspace.error, '');
    assert.equal(calls.length, 1);
});

test('a successful deletion still reloads the previous page when cache refresh fails', async () => {
    const response = {
        data: [{ type: 'admin-roles', id: '17', attributes: { name: 'Last row' } }],
        meta: { delete: true, page: { currentPage: 2, perPage: 1, total: 2 } }
    };
    const { workspace, calls } = fixture('roles', response, {
        remove: async () => {
            response.data = [];
        },
        refresh: async () => {
            throw new Error('Cache unavailable');
        }
    });
    workspace.pagination = { pageIndex: 1, pageSize: 1 };
    await workspace.load();
    workspace.remove(workspace.rows[0], null);
    await workspace.confirm();
    assert.equal(workspace.notice, 'admin.saved_refresh');
    assert.equal(workspace.error, '');
    assert.deepEqual(workspace.rows, []);
    assert.equal(new URL(calls[1].url).searchParams.get('page[number]'), '1');
});

test('invalid action responses cannot open a result dialog', async () => {
    for (const response of [{}, { tools: [] }, { models: [{ model_id: 'example', label: 42 }] }]) {
        const { workspace, restApi } = fixture<{ discover: ProviderDiscovery }>(
            'providers',
            { data: [] },
            {
                rowActions: (row) => ({
                    discover: {
                        confirm: true,
                        dialog: true,
                        run: () =>
                            restApi.postToResourceAction(
                                'admin-providers',
                                `${row.id}/actions/discover`,
                                {},
                                {
                                    schema: ProviderDiscoverySchema
                                }
                            )
                    }
                })
            },
            response
        );
        workspace.action(workspace.rowActions({ id: '17' })[0]);
        await workspace.confirm();
        assert.deepEqual(workspace.results, {});
        assert.notEqual(workspace.error, '');
        assert.equal(workspace.busy, false);
    }
});

test('actions without a dialog discard their response', async () => {
    const { workspace } = fixture(
        'providers',
        { data: [] },
        {
            rowActions: () => ({ test: { confirm: true, run: async () => ({ queued: true }) } })
        }
    );
    workspace.action(workspace.rowActions({ id: '17' })[0]);
    await workspace.confirm();
    assert.deepEqual(workspace.results, {});
    assert.equal(workspace.notice, 'admin.action_done');
});

test('workspace row actions retain row context and reflect current permissions', async () => {
    let allowed = false;
    const executed: string[] = [];
    const { workspace } = fixture(
        'providers',
        { data: [] },
        {
            rowActions: (row) => ({
                test:
                    allowed ?
                        {
                            confirm: true,
                            run: async () => {
                                executed.push(row.id);
                            }
                        }
                    :   undefined
            })
        }
    );
    const row = { id: '17' };
    assert.deepEqual(workspace.rowActions(row), []);
    allowed = true;
    workspace.action(workspace.rowActions(row)[0]);
    assert.deepEqual(executed, []);
    await workspace.confirm();
    assert.deepEqual(executed, ['17']);
    allowed = false;
    assert.deepEqual(workspace.rowActions(row), []);
    assert.deepEqual(fixture('environment').workspace.rowActions(row), []);
});

test('results are independent by action id and retain their own row and trigger', async () => {
    const { workspace } = fixture(
        'providers',
        { data: [] },
        {
            rowActions: (row) => ({
                models: { confirm: true, dialog: true, run: async () => ({ models: [row.id] }) },
                tools: { confirm: true, dialog: true, run: async () => ({ tools: [row.id] }) }
            })
        }
    );
    // Class instances behave like DOM nodes in rune state without requiring a browser.
    class FocusTarget {
        isConnected = true;
    }
    const firstTrigger = new FocusTarget() as HTMLElement;
    const secondTrigger = new FocusTarget() as HTMLElement;
    workspace.action(workspace.rowActions({ id: '17' })[0], firstTrigger);
    await workspace.confirm();
    workspace.action(workspace.rowActions({ id: '23' })[1], secondTrigger);
    await workspace.confirm();
    assert.deepEqual(workspace.results.models.response, { models: ['17'] });
    assert.deepEqual(workspace.results.tools.response, { tools: ['23'] });
    assert.equal(workspace.results.models.id, '17');
    assert.equal(workspace.results.tools.id, '23');
    assert.equal(workspace.results.models.trigger, firstTrigger);
    assert.equal(workspace.results.tools.trigger, secondTrigger);
    assert.equal(workspace.restoreFocus(workspace.results.models.trigger), firstTrigger);
    assert.equal(workspace.restoreFocus(workspace.results.tools.trigger), secondTrigger);
    workspace.closeResult('models');
    assert.equal(workspace.results.models, undefined);
    assert.deepEqual(workspace.results.tools.response, { tools: ['23'] });
    workspace.closeResult('tools');
    assert.deepEqual(workspace.results, {});
});

test('revocation clears protected rows and dialogs and a later grant cannot revive confirmation', async () => {
    let writes = 0;
    const { workspace } = fixture(
        'roles',
        {
            data: [{ type: 'admin-roles', id: '17', attributes: { name: 'Protected role' } }],
            meta: { fields: [{ key: 'name', type: 'text' }], delete: true }
        },
        {
            save: async () => {
                writes++;
            },
            remove: async () => {
                writes++;
            }
        }
    );
    await workspace.load();
    workspace.edit(workspace.rows[0], null);
    workspace.remove(workspace.rows[0], null);
    assert.ok(workspace.editor);
    assert.ok(workspace.confirmation);
    workspace.invalidate();
    assert.equal(workspace.content, null);
    assert.deepEqual(workspace.rows, []);
    assert.equal(workspace.editor, null);
    assert.equal(workspace.confirmation, null);
    await workspace.confirm();
    await assert.rejects(workspace.save({ name: 'Forbidden' }));
    assert.equal(writes, 0);
    await workspace.resume();
    await workspace.confirm();
    assert.equal(writes, 0);
    assert.equal(workspace.rows.length, 1);
    assert.equal(workspace.editor, null);
});

test('late reads from a revoked actor are discarded even when the reader ignores abort', async () => {
    let resolve!: (value: JsonApiCollection<AdminRow>) => void;
    const pending = new Promise<JsonApiCollection<AdminRow>>((done) => {
        resolve = done;
    });
    const workspace = new AdminWorkspace(
        (label) => label,
        [{ id: 'name' }],
        () => pending
    );
    const read = workspace.load();
    workspace.invalidate();
    resolve([{ type: 'admin-roles', id: '17', name: 'Former actor data' }]);
    await read;
    assert.equal(workspace.content, null);
    assert.equal(workspace.loading, false);
});

test('late action responses cannot restore dialogs after revocation and regrant', async () => {
    let finish!: (value: { tokens: string[] }) => void;
    const response = new Promise<{ tokens: string[] }>((resolve) => {
        finish = resolve;
    });
    const { workspace } = fixture(
        'users',
        { data: [] },
        {
            rowActions: () => ({ tokens: { confirm: true, dialog: true, run: () => response } })
        }
    );
    workspace.action(workspace.rowActions({ id: '17' })[0]);
    const action = workspace.confirm();
    workspace.invalidate();
    await workspace.resume();
    finish({ tokens: ['Former actor token metadata'] });
    await action;
    assert.deepEqual(workspace.results, {});
    assert.equal(workspace.confirmation, null);
    assert.equal(workspace.notice, '');
});

test('suspending a refresh cancels reads and prevents actions until fresh metadata loads', async () => {
    let calls = 0;
    const { workspace } = fixture(
        'users',
        { data: [] },
        {
            save: async () => {
                calls++;
            }
        }
    );
    workspace.suspend();
    workspace.edit(null, null);
    workspace.action({
        id: 'tokens',
        run: async () => {
            calls++;
        }
    });
    assert.equal(workspace.editor, null);
    await assert.rejects(workspace.save({}));
    await workspace.load();
    assert.equal(workspace.content, null);
    assert.equal(calls, 0);
    await workspace.resume();
    workspace.edit(null, null);
    assert.ok(workspace.editor);
});

test('403 denial stays visible through refresh without replaying the mutation or keeping protected details', async () => {
    const { ApiTransportError } = await import('../../../resources/js/kernel/api/errors.js');
    let writes = 0;
    const { workspace } = fixture(
        'users',
        { data: [] },
        {
            save: async () => {
                writes++;
                workspace.suspend();
                throw new ApiTransportError(403, [], {}, 'Protected account metadata');
            }
        }
    );
    await workspace.update({ id: '17' }, {});
    assert.equal(workspace.authorizationDenied, true);
    assert.equal(workspace.error, '');
    await workspace.resume();
    assert.equal(workspace.authorizationDenied, true);
    assert.equal(writes, 1);
});

test('focus restoration falls back when a refresh disabled the original trigger', () => {
    const { workspace } = fixture('users');
    class FocusTarget {
        isConnected = true;
        matches() {
            return true;
        }
    }
    const disabled = new FocusTarget() as unknown as HTMLElement;
    const fallback = new FocusTarget() as unknown as HTMLElement;
    workspace.focusFallback = () => fallback;
    assert.equal(workspace.restoreFocus(disabled), fallback);
});

function refreshFixture() {
    const response = {
        data: [{ type: 'admin-roles', id: '17', attributes: { name: 'Role' }, meta: { version: 'v1' } }],
        meta: { fields: [{ key: 'name', type: 'text' }], create: true, delete: true }
    };
    const { workspace } = fixture('roles', response, { save: async () => {}, remove: async () => {} });
    return { response, workspace };
}

test('routine revalidation stays silent while it reads and publishes fresh rows', async () => {
    const first: JsonApiCollection<AdminRow> = Object.assign(
        [{ type: 'admin-roles', id: '17', name: 'Old', _meta: { version: 'v1' } }],
        { _meta: { fields: [{ key: 'name', type: 'text' }] } }
    );
    const second: JsonApiCollection<AdminRow> = Object.assign(
        [{ type: 'admin-roles', id: '17', name: 'Fresh', _meta: { version: 'v2' } }],
        { _meta: { fields: [{ key: 'name', type: 'text' }] } }
    );
    let finish!: (value: JsonApiCollection<AdminRow>) => void;
    const pending = new Promise<JsonApiCollection<AdminRow>>((resolve) => {
        finish = resolve;
    });
    let reads = 0;
    const workspace = new AdminWorkspace(
        (label) => label,
        [{ id: 'name' }],
        () => (reads++ === 0 ? Promise.resolve(first) : pending)
    );
    await workspace.load();
    const revalidation = workspace.revalidate();
    assert.equal(workspace.loading, false);
    assert.equal(workspace.rows[0].name, 'Old');
    finish(second);
    await revalidation;
    assert.equal(workspace.loading, false);
    assert.equal(workspace.rows[0].name, 'Fresh');
});

test('routine revalidation keeps pending confirmations open', async () => {
    const { workspace } = refreshFixture();
    await workspace.load();
    workspace.remove(workspace.rows[0], null);
    const confirmation = workspace.confirmation;
    assert.equal(await workspace.revalidate(), false);
    assert.equal(workspace.confirmation, confirmation);
});

test('routine revalidation preserves an unchanged editor instance and updates its row reference', async () => {
    const { workspace } = refreshFixture();
    await workspace.load();
    const previousRow = workspace.rows[0];
    workspace.edit(previousRow, null);
    const editor = workspace.editor;
    assert.equal(await workspace.revalidate(), false);
    assert.equal(workspace.editor, editor, 'keeping the editor instance preserves its form draft');
    assert.notEqual(workspace.editor?.row, previousRow);
    assert.equal(workspace.editor?.row, workspace.rows[0]);
});

test('routine revalidation closes changed or deleted edit targets', async () => {
    for (const change of ['changed', 'deleted']) {
        const { response, workspace } = refreshFixture();
        await workspace.load();
        workspace.edit(workspace.rows[0], null);
        if (change === 'changed') response.data[0].meta.version = 'v2';
        else response.data = [];
        assert.equal(await workspace.revalidate(), true);
        assert.equal(workspace.editor, null, change);
        assert.equal(workspace.notice, 'admin.editor_refreshed');
    }
});

test('routine revalidation is a no-op while suspended', async () => {
    const { workspace, calls } = fixture('roles');
    workspace.suspend();
    assert.equal(await workspace.revalidate(), false);
    assert.equal(calls.length, 0);
});

test('failed routine revalidation preserves the editor and previous content', async () => {
    const { response, workspace } = refreshFixture();
    await workspace.load();
    workspace.edit(workspace.rows[0], null);
    const content = workspace.content;
    const editor = workspace.editor;
    Object.assign(response.meta, { fields: 'invalid response' });
    assert.equal(await workspace.revalidate(), false);
    assert.equal(workspace.content, content);
    assert.equal(workspace.editor, editor);
    assert.notEqual(workspace.error, '');
});

test('successful same-permission refresh closes changed or deleted edit targets', async () => {
    for (const change of ['changed', 'deleted']) {
        const { response, workspace } = refreshFixture();
        await workspace.load();
        workspace.edit(workspace.rows[0], null);
        workspace.suspend();
        if (change === 'changed') response.data[0].meta.version = 'v2';
        else response.data = [];
        await workspace.resume();
        assert.equal(workspace.editor, null, change);
        assert.equal(workspace.notice, 'admin.editor_refreshed');
    }
});

test('suspend/resume refresh always closes pending confirmations', async () => {
    const { workspace } = refreshFixture();
    await workspace.load();
    workspace.remove(workspace.rows[0], null);
    workspace.suspend();
    await workspace.resume();
    assert.equal(workspace.confirmation, null);
});

test('successful same-permission refresh preserves unchanged create and edit editor identities', async () => {
    for (const creating of [false, true]) {
        const { workspace } = refreshFixture();
        await workspace.load();
        workspace.edit(creating ? null : workspace.rows[0], null);
        const editor = workspace.editor;
        workspace.suspend();
        await workspace.resume();
        assert.equal(workspace.editor, editor, 'keeping the editor instance preserves its form draft');
        assert.equal(workspace.notice, '');
    }
});

test('refresh closes editors when field metadata or create access changes even if a row version is unchanged', async () => {
    for (const change of ['fields', 'create']) {
        const { response, workspace } = refreshFixture();
        await workspace.load();
        workspace.edit(change === 'create' ? null : workspace.rows[0], null);
        workspace.suspend();
        if (change === 'fields') response.meta.fields = [];
        else response.meta.create = false;
        await workspace.resume();
        assert.equal(workspace.editor, null);
    }
});

test('same-version editors close when authorization catalog metadata changes', async () => {
    const { response, workspace } = refreshFixture();
    await workspace.load();
    workspace.edit(workspace.rows[0], null);
    Object.assign(response.meta, {
        permission_catalog: [
            {
                name: 'tools.use',
                group: 'tools',
                title_label: 'admin.permissions.tools.use.title',
                description_label: 'admin.permissions.tools.use.description',
                grantable: false
            }
        ]
    });
    workspace.suspend();
    await workspace.resume();
    assert.equal(workspace.editor, null);
});

test('failed refresh closes unverified editors and clears old editable metadata', async () => {
    const { response, workspace } = refreshFixture();
    await workspace.load();
    workspace.edit(workspace.rows[0], null);
    Object.assign(response.meta, { fields: 'invalid response' });
    workspace.suspend();
    await workspace.resume();
    assert.equal(workspace.editor, null);
    assert.equal(workspace.content, null);
    assert.equal(workspace.canEdit, false);
    assert.equal(workspace.notice, 'admin.editor_unverified');
    assert.notEqual(workspace.error, '');
});
