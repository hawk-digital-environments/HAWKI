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
import { builtInWorkspaces, type WorkspaceId } from '../../../resources/js/plugins/admin/workspaces.js';
import { AdminRecordSet, type AdminRecordSetOptions } from '../../../resources/js/plugins/admin/recordSet.svelte.js';

function fixture<Results extends Record<string, unknown> = {}>(
    workspace: WorkspaceId,
    response: unknown = { data: [], meta: {} },
    options: AdminRecordSetOptions<AdminRow, Results> = {},
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
    const recordSet = new AdminRecordSet<AdminRow, string, Results>(
        (label) => label,
        [{ id: 'kind' }],
        (signal, query) => restApi.getResourceCollection(`admin-${workspace}`, { query, signal }),
        options
    );
    return { recordSet, calls, restApi };
}

test('record set delegates saves, updates, removal and refresh without an application or Workspace page', async () => {
    const events: unknown[] = [];
    const { recordSet } = fixture(
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
    await recordSet.load();
    assert.equal(recordSet.canCreate, true);
    assert.equal(recordSet.canEdit, true);
    assert.equal(recordSet.canDelete, true);
    await recordSet.save({ name: 'Created' });
    const row = recordSet.rows[0];
    recordSet.edit(row, null);
    await recordSet.save({ name: 'Edited' });
    await recordSet.update(row, { name: 'Updated' });
    recordSet.remove(row, null);
    assert.equal(events.length, 6, 'removal waits for confirmation');
    await recordSet.confirm();
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

test('record set reads send standard table queries and abort superseded requests', async () => {
    const { recordSet, calls } = fixture('models');
    recordSet.pagination = { pageIndex: 1, pageSize: 10 };
    recordSet.sorting = [{ id: 'label', desc: true }];
    recordSet.search = 'a & b';
    await recordSet.load();
    const url = new URL(calls[0].url);
    assert.equal(url.searchParams.get('page[number]'), '2');
    assert.equal(url.searchParams.get('page[size]'), '10');
    assert.equal(url.searchParams.get('sort'), '-label');
    assert.equal(url.searchParams.get('filter[search]'), 'a & b');
    await recordSet.load();
    assert.equal(calls[0].options.signal?.aborted, true);
    recordSet.dispose();
    assert.equal(calls[1].options.signal?.aborted, true);
});

test('configured actions retain their result and row context after confirmation', async () => {
    const response = { models: [{ model_id: 'example', label: 'Example' }] };
    const { recordSet, restApi } = fixture<{ discover: ProviderDiscovery }>(
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
    recordSet.action(recordSet.rowActions({ id: '17' })[0]);
    assert.deepEqual(recordSet.results, {});
    await recordSet.confirm();
    assert.deepEqual(recordSet.results.discover, { id: '17', response, trigger: null });
    assert.equal(recordSet.error, '');
    assert.equal(recordSet.busy, false);
    recordSet.closeResult('discover');
    assert.deepEqual(recordSet.results, {});
});

test('server side actions refresh the dependent caches like writes do', async () => {
    const events: string[] = [];
    const { recordSet, calls } = fixture(
        'models',
        { data: [] },
        {
            rowActions: () => ({ refresh: { confirm: true, run: async () => events.push('run') } }),
            refresh: async () => {
                events.push('refresh');
            }
        }
    );
    recordSet.action(recordSet.rowActions({ id: '17' })[0]);
    await recordSet.confirm();
    assert.deepEqual(events, ['run', 'refresh']);
    assert.equal(recordSet.notice, 'admin.action_done');
    assert.equal(calls.filter((call) => call.options.method === 'GET').length, 1, 'the table reloads after the caches');
});

test('every workspace decodes collection metadata and edit versions', async () => {
    for (const workspace of builtInWorkspaces) {
        const { recordSet, calls } = fixture(workspace.id, {
            data: [{ type: `admin-${workspace.id}`, id: '17', attributes: { name: 'Example' }, meta: { version: 'v1' } }],
            meta: {
                fields: [{ key: 'name', type: 'text' }],
                create: true,
                page: { currentPage: 2, perPage: 1, total: 3 }
            }
        });
        await recordSet.load();
        assert.equal(new URL(calls[0].url).pathname, `/api/hawki/v1/admin-${workspace.id}`);
        assert.equal(recordSet.rows[0].id, '17');
        assert.equal(recordSet.rows[0].name, 'Example');
        assert.equal(recordSet.rows[0]._version, 'v1');
        assert.equal(recordSet.total, 3);
        assert.equal(recordSet.content?.page, 2);
        assert.equal(recordSet.content?.size, 1);
        assert.equal(recordSet.fields[0].key, 'name');
        assert.equal(recordSet.canCreate, false, 'server metadata cannot enable an absent save operation');
    }
});

test('invalid collection responses leave a visible load error', async () => {
    for (const response of [
        { content: { rows: [] } },
        { data: [{ type: 'admin-models', attributes: {} }] },
        { data: [], meta: { fields: 'invalid' } }
    ]) {
        const { recordSet } = fixture('models', response);
        await recordSet.load();
        assert.notEqual(recordSet.error, '');
        assert.equal(recordSet.content, null);
        assert.equal(recordSet.loading, false);
    }
});

test('domain types keep their kind attribute and are never confused with the resource type', async () => {
    const { recordSet, calls } = fixture('announcements', {
        data: [{ type: 'admin-announcements', id: '17', attributes: { kind: 'news' } }]
    });
    recordSet.sorting = [{ id: 'kind', desc: true }];
    await recordSet.load();
    assert.equal(recordSet.rows[0].kind, 'news');
    assert.equal(recordSet.rows[0].type, undefined);
    assert.equal(new URL(calls[0].url).searchParams.get('sort'), '-kind');
});

test('read-only workspaces cannot edit, create, remove or reset records', async () => {
    const { recordSet } = fixture(
        'environment',
        {
            data: [],
            meta: { create: true, delete: true, fields: [{ key: 'name', type: 'text' }] }
        },
        { resettable: true }
    );
    await recordSet.load();
    assert.equal(recordSet.canCreate, false);
    assert.equal(recordSet.canEdit, false);
    assert.equal(recordSet.canDelete, false);
    assert.equal(recordSet.resettable, false);
    recordSet.remove({ id: '17' }, null);
    assert.equal(recordSet.confirmation, null);
    await assert.rejects(recordSet.save({}), /admin.errors.save/);
});

test('failed mutations release row locks and do not refresh', async () => {
    let refreshed = false;
    const { recordSet } = fixture(
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
    await recordSet.update(row, {});
    assert.equal(recordSet.error, 'Conflict');
    assert.equal(recordSet.locked(row), false);
    assert.equal(refreshed, false);
});

test('a successful save still reloads when the supplied cache refresh fails', async () => {
    const { recordSet, calls } = fixture(
        'roles',
        { data: [] },
        {
            save: async () => {},
            refresh: async () => {
                throw new Error('Cache unavailable');
            }
        }
    );
    await recordSet.save({});
    assert.equal(recordSet.notice, 'admin.saved_refresh');
    assert.equal(recordSet.error, '');
    assert.equal(calls.length, 1);
});

test('a successful deletion still reloads the previous page when cache refresh fails', async () => {
    const response = {
        data: [{ type: 'admin-roles', id: '17', attributes: { name: 'Last row' } }],
        meta: { delete: true, page: { currentPage: 2, perPage: 1, total: 2 } }
    };
    const { recordSet, calls } = fixture('roles', response, {
        remove: async () => {
            response.data = [];
        },
        refresh: async () => {
            throw new Error('Cache unavailable');
        }
    });
    recordSet.pagination = { pageIndex: 1, pageSize: 1 };
    await recordSet.load();
    recordSet.remove(recordSet.rows[0], null);
    await recordSet.confirm();
    assert.equal(recordSet.notice, 'admin.saved_refresh');
    assert.equal(recordSet.error, '');
    assert.deepEqual(recordSet.rows, []);
    assert.equal(new URL(calls[1].url).searchParams.get('page[number]'), '1');
});

test('invalid action responses cannot open a result dialog', async () => {
    for (const response of [{}, { tools: [] }, { models: [{ model_id: 'example', label: 42 }] }]) {
        const { recordSet, restApi } = fixture<{ discover: ProviderDiscovery }>(
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
        recordSet.action(recordSet.rowActions({ id: '17' })[0]);
        await recordSet.confirm();
        assert.deepEqual(recordSet.results, {});
        assert.notEqual(recordSet.error, '');
        assert.equal(recordSet.busy, false);
    }
});

test('actions without a dialog discard their response', async () => {
    const { recordSet } = fixture(
        'providers',
        { data: [] },
        {
            rowActions: () => ({ test: { confirm: true, run: async () => ({ queued: true }) } })
        }
    );
    recordSet.action(recordSet.rowActions({ id: '17' })[0]);
    await recordSet.confirm();
    assert.deepEqual(recordSet.results, {});
    assert.equal(recordSet.notice, 'admin.action_done');
});

test('record set row actions retain row context and reflect current permissions', async () => {
    let allowed = false;
    const executed: string[] = [];
    const { recordSet } = fixture(
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
    assert.deepEqual(recordSet.rowActions(row), []);
    allowed = true;
    recordSet.action(recordSet.rowActions(row)[0]);
    assert.deepEqual(executed, []);
    await recordSet.confirm();
    assert.deepEqual(executed, ['17']);
    allowed = false;
    assert.deepEqual(recordSet.rowActions(row), []);
    assert.deepEqual(fixture('environment').recordSet.rowActions(row), []);
});

test('results are independent by action id and retain their own row and trigger', async () => {
    const { recordSet } = fixture(
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
    recordSet.action(recordSet.rowActions({ id: '17' })[0], firstTrigger);
    await recordSet.confirm();
    recordSet.action(recordSet.rowActions({ id: '23' })[1], secondTrigger);
    await recordSet.confirm();
    assert.deepEqual(recordSet.results.models.response, { models: ['17'] });
    assert.deepEqual(recordSet.results.tools.response, { tools: ['23'] });
    assert.equal(recordSet.results.models.id, '17');
    assert.equal(recordSet.results.tools.id, '23');
    assert.equal(recordSet.results.models.trigger, firstTrigger);
    assert.equal(recordSet.results.tools.trigger, secondTrigger);
    assert.equal(recordSet.restoreFocus(recordSet.results.models.trigger), firstTrigger);
    assert.equal(recordSet.restoreFocus(recordSet.results.tools.trigger), secondTrigger);
    recordSet.closeResult('models');
    assert.equal(recordSet.results.models, undefined);
    assert.deepEqual(recordSet.results.tools.response, { tools: ['23'] });
    recordSet.closeResult('tools');
    assert.deepEqual(recordSet.results, {});
});

test('revocation clears protected rows and dialogs and a later grant cannot revive confirmation', async () => {
    let writes = 0;
    const { recordSet } = fixture(
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
    await recordSet.load();
    recordSet.edit(recordSet.rows[0], null);
    recordSet.remove(recordSet.rows[0], null);
    assert.ok(recordSet.editor);
    assert.ok(recordSet.confirmation);
    recordSet.invalidate();
    assert.equal(recordSet.content, null);
    assert.deepEqual(recordSet.rows, []);
    assert.equal(recordSet.editor, null);
    assert.equal(recordSet.confirmation, null);
    await recordSet.confirm();
    await assert.rejects(recordSet.save({ name: 'Forbidden' }));
    assert.equal(writes, 0);
    await recordSet.resume();
    await recordSet.confirm();
    assert.equal(writes, 0);
    assert.equal(recordSet.rows.length, 1);
    assert.equal(recordSet.editor, null);
});

test('late reads from a revoked actor are discarded even when the reader ignores abort', async () => {
    let resolve!: (value: JsonApiCollection<AdminRow>) => void;
    const pending = new Promise<JsonApiCollection<AdminRow>>((done) => {
        resolve = done;
    });
    const recordSet = new AdminRecordSet(
        (label) => label,
        [{ id: 'name' }],
        () => pending
    );
    const read = recordSet.load();
    recordSet.invalidate();
    resolve([{ type: 'admin-roles', id: '17', name: 'Former actor data' }]);
    await read;
    assert.equal(recordSet.content, null);
    assert.equal(recordSet.loading, false);
});

test('late action responses cannot restore dialogs after revocation and regrant', async () => {
    let finish!: (value: { tokens: string[] }) => void;
    const response = new Promise<{ tokens: string[] }>((resolve) => {
        finish = resolve;
    });
    const { recordSet } = fixture(
        'users',
        { data: [] },
        {
            rowActions: () => ({ tokens: { confirm: true, dialog: true, run: () => response } })
        }
    );
    recordSet.action(recordSet.rowActions({ id: '17' })[0]);
    const action = recordSet.confirm();
    recordSet.invalidate();
    await recordSet.resume();
    finish({ tokens: ['Former actor token metadata'] });
    await action;
    assert.deepEqual(recordSet.results, {});
    assert.equal(recordSet.confirmation, null);
    assert.equal(recordSet.notice, '');
});

test('suspending a refresh cancels reads and prevents actions until fresh metadata loads', async () => {
    let calls = 0;
    const { recordSet } = fixture(
        'users',
        { data: [] },
        {
            save: async () => {
                calls++;
            }
        }
    );
    recordSet.suspend();
    recordSet.edit(null, null);
    recordSet.action({
        id: 'tokens',
        run: async () => {
            calls++;
        }
    });
    assert.equal(recordSet.editor, null);
    await assert.rejects(recordSet.save({}));
    await recordSet.load();
    assert.equal(recordSet.content, null);
    assert.equal(calls, 0);
    await recordSet.resume();
    recordSet.edit(null, null);
    assert.ok(recordSet.editor);
});

test('403 denial stays visible through refresh without replaying the mutation or keeping protected details', async () => {
    const { ApiTransportError } = await import('../../../resources/js/kernel/api/errors.js');
    let writes = 0;
    const { recordSet } = fixture(
        'users',
        { data: [] },
        {
            save: async () => {
                writes++;
                recordSet.suspend();
                throw new ApiTransportError(403, [], {}, 'Protected account metadata');
            }
        }
    );
    await recordSet.update({ id: '17' }, {});
    assert.equal(recordSet.authorizationDenied, true);
    assert.equal(recordSet.error, '');
    await recordSet.resume();
    assert.equal(recordSet.authorizationDenied, true);
    assert.equal(writes, 1);
});

test('focus restoration falls back when a refresh disabled the original trigger', () => {
    const { recordSet } = fixture('users');
    class FocusTarget {
        isConnected = true;
        matches() {
            return true;
        }
    }
    const disabled = new FocusTarget() as unknown as HTMLElement;
    const fallback = new FocusTarget() as unknown as HTMLElement;
    recordSet.focusFallback = () => fallback;
    assert.equal(recordSet.restoreFocus(disabled), fallback);
});

function refreshFixture() {
    const response = {
        data: [{ type: 'admin-roles', id: '17', attributes: { name: 'Role' }, meta: { version: 'v1' } }],
        meta: { fields: [{ key: 'name', type: 'text' }], create: true, delete: true }
    };
    const { recordSet } = fixture('roles', response, { save: async () => {}, remove: async () => {} });
    return { response, recordSet };
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
    const recordSet = new AdminRecordSet(
        (label) => label,
        [{ id: 'name' }],
        () => (reads++ === 0 ? Promise.resolve(first) : pending)
    );
    await recordSet.load();
    const revalidation = recordSet.revalidate();
    assert.equal(recordSet.loading, false);
    assert.equal(recordSet.rows[0].name, 'Old');
    finish(second);
    await revalidation;
    assert.equal(recordSet.loading, false);
    assert.equal(recordSet.rows[0].name, 'Fresh');
});

test('routine revalidation keeps pending confirmations open', async () => {
    const { recordSet } = refreshFixture();
    await recordSet.load();
    recordSet.remove(recordSet.rows[0], null);
    const confirmation = recordSet.confirmation;
    assert.equal(await recordSet.revalidate(), false);
    assert.equal(recordSet.confirmation, confirmation);
});

test('routine revalidation preserves an unchanged editor instance and updates its row reference', async () => {
    const { recordSet } = refreshFixture();
    await recordSet.load();
    const previousRow = recordSet.rows[0];
    recordSet.edit(previousRow, null);
    const editor = recordSet.editor;
    assert.equal(await recordSet.revalidate(), false);
    assert.equal(recordSet.editor, editor, 'keeping the editor instance preserves its form draft');
    assert.notEqual(recordSet.editor?.row, previousRow);
    assert.equal(recordSet.editor?.row, recordSet.rows[0]);
});

test('routine revalidation closes changed or deleted edit targets', async () => {
    for (const change of ['changed', 'deleted']) {
        const { response, recordSet } = refreshFixture();
        await recordSet.load();
        recordSet.edit(recordSet.rows[0], null);
        if (change === 'changed') response.data[0].meta.version = 'v2';
        else response.data = [];
        assert.equal(await recordSet.revalidate(), true);
        assert.equal(recordSet.editor, null, change);
        assert.equal(recordSet.notice, 'admin.editor_refreshed');
    }
});

test('routine revalidation is a no-op while suspended', async () => {
    const { recordSet, calls } = fixture('roles');
    recordSet.suspend();
    assert.equal(await recordSet.revalidate(), false);
    assert.equal(calls.length, 0);
});

test('failed routine revalidation preserves the editor and previous content', async () => {
    const { response, recordSet } = refreshFixture();
    await recordSet.load();
    recordSet.edit(recordSet.rows[0], null);
    const content = recordSet.content;
    const editor = recordSet.editor;
    Object.assign(response.meta, { fields: 'invalid response' });
    assert.equal(await recordSet.revalidate(), false);
    assert.equal(recordSet.content, content);
    assert.equal(recordSet.editor, editor);
    assert.notEqual(recordSet.error, '');
});

test('successful same-permission refresh closes changed or deleted edit targets', async () => {
    for (const change of ['changed', 'deleted']) {
        const { response, recordSet } = refreshFixture();
        await recordSet.load();
        recordSet.edit(recordSet.rows[0], null);
        recordSet.suspend();
        if (change === 'changed') response.data[0].meta.version = 'v2';
        else response.data = [];
        await recordSet.resume();
        assert.equal(recordSet.editor, null, change);
        assert.equal(recordSet.notice, 'admin.editor_refreshed');
    }
});

test('suspend/resume refresh always closes pending confirmations', async () => {
    const { recordSet } = refreshFixture();
    await recordSet.load();
    recordSet.remove(recordSet.rows[0], null);
    recordSet.suspend();
    await recordSet.resume();
    assert.equal(recordSet.confirmation, null);
});

test('successful same-permission refresh preserves unchanged create and edit editor identities', async () => {
    for (const creating of [false, true]) {
        const { recordSet } = refreshFixture();
        await recordSet.load();
        recordSet.edit(creating ? null : recordSet.rows[0], null);
        const editor = recordSet.editor;
        recordSet.suspend();
        await recordSet.resume();
        assert.equal(recordSet.editor, editor, 'keeping the editor instance preserves its form draft');
        assert.equal(recordSet.notice, '');
    }
});

test('refresh closes editors when field metadata or create access changes even if a row version is unchanged', async () => {
    for (const change of ['fields', 'create']) {
        const { response, recordSet } = refreshFixture();
        await recordSet.load();
        recordSet.edit(change === 'create' ? null : recordSet.rows[0], null);
        recordSet.suspend();
        if (change === 'fields') response.meta.fields = [];
        else response.meta.create = false;
        await recordSet.resume();
        assert.equal(recordSet.editor, null);
    }
});

test('failed refresh closes unverified editors and clears old editable metadata', async () => {
    const { response, recordSet } = refreshFixture();
    await recordSet.load();
    recordSet.edit(recordSet.rows[0], null);
    Object.assign(response.meta, { fields: 'invalid response' });
    recordSet.suspend();
    await recordSet.resume();
    assert.equal(recordSet.editor, null);
    assert.equal(recordSet.content, null);
    assert.equal(recordSet.canEdit, false);
    assert.equal(recordSet.notice, 'admin.editor_unverified');
    assert.notEqual(recordSet.error, '');
});
