import { strict as assert } from 'node:assert';
import { test } from 'node:test';
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
    options: AdminWorkspaceOptions<Results> = {},
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
    const workspace = new AdminWorkspace<Results>(
        (label) => label,
        [{ id: 'type', sortKey: 'kind' }],
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

test('domain types survive decoding and sorting without colliding with resource types', async () => {
    const { workspace, calls } = fixture('announcements', {
        data: [{ type: 'admin-announcements', id: '17', attributes: { kind: 'news' } }]
    });
    workspace.sorting = [{ id: 'type', desc: true }];
    await workspace.load();
    assert.equal(workspace.rows[0].type, 'news');
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
