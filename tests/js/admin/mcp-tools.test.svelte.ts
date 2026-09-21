import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { RestApi } from '../../../resources/js/kernel/api/RestApi.js';
import { UriBuilder } from '../../../resources/js/kernel/api/UriBuilder.js';
import { revealServerId } from '../../../resources/js/plugins/admin/mcp.js';
import { AdminRowSchema, type AdminRow } from '../../../resources/js/plugins/admin/schemas/admin-content.js';
import { AdminWorkspace } from '../../../resources/js/plugins/admin/workspace.svelte.js';

/** One page of the tools section, as the admin API returns it for a server. */
function toolsPage(ids: string[], total = ids.length) {
    return {
        data: ids.map((id) => ({
            type: 'admin-tools',
            id,
            attributes: { name: `Tool ${id}`, active: true },
            meta: { version: `v-${id}` }
        })),
        meta: {
            fields: [{ key: 'active', type: 'boolean' }],
            page: { currentPage: 1, perPage: 100, total }
        }
    };
}

/** A response the test hands out when the read reaches the transport. */
function deferred() {
    let resolve!: (value: unknown) => void;
    return { promise: new Promise<unknown>((settle) => (resolve = settle)), resolve };
}

/** The tools Workspace of the MCP page: one section, read through the expanded server's value filter. */
function fixture(serverId: string) {
    const reads: string[] = [];
    const writes: { id: string | null; values: Record<string, unknown> }[] = [];
    let respond: () => Promise<unknown> = async () => toolsPage([]);
    let started = deferred();
    const restApi = new RestApi(
        new UriBuilder('https://hawki.test'),
        async (url) => {
            reads.push(url);
            started.resolve(url);
            return respond();
        },
        () => {
            throw new Error('Connection not loaded');
        },
        () => AdminRowSchema
    );
    const tools = new AdminWorkspace<AdminRow>(
        (label) => label,
        [{ id: 'name' }, { id: 'active', sortable: false }],
        (signal, query) => restApi.getResourceCollection('admin-tools', { query, signal }),
        {
            columnFilters: [{ id: 'mcp_server_id', value: serverId }],
            save: async (values, row) => {
                writes.push({ id: row?.id ?? null, values });
            }
        }
    );
    tools.pagination = { pageIndex: 0, pageSize: 100 };
    return {
        tools,
        reads,
        writes,
        /** Answers the next reads; `started` resolves once a read reached the transport. */
        answer(response: () => Promise<unknown>) {
            started = deferred();
            respond = response;
        },
        get started() {
            return started.promise;
        }
    };
}

test('tools of the previous server can neither be shown nor edited while the selected one loads', async () => {
    const section = fixture('1');
    const { tools, writes, answer } = section;
    answer(async () => toolsPage(['10', '11']));
    await tools.load();
    assert.equal(tools.stale, false);
    assert.equal(tools.locked(tools.rows[0]), false);

    const pending = deferred();
    answer(() => pending.promise);
    const switched = tools.applyColumnFilters([{ id: 'mcp_server_id', value: '2' }]);
    await section.started;
    // The rows of server 1 are still the published content, but they belong to the previous selection.
    assert.deepEqual(
        tools.rows.map((row) => row.id),
        ['10', '11']
    );
    assert.equal(tools.stale, true, 'the page must not render them under server 2');
    assert.equal(tools.locked(tools.rows[0]), true);
    await tools.update(tools.rows[0], { active: false });
    assert.deepEqual(writes, [], 'a locked row is not saved');

    pending.resolve(toolsPage(['20']));
    await switched;
    assert.deepEqual(
        tools.rows.map((row) => row.id),
        ['20']
    );
    assert.equal(tools.stale, false);
    assert.equal(tools.locked(tools.rows[0]), false);
    await tools.update(tools.rows[0], { active: false });
    assert.deepEqual(writes, [{ id: '20', values: { active: false } }]);
});

test('a failed read for the selected server leaves no editable rows of the previous one', async () => {
    const { tools, writes, answer } = fixture('1');
    answer(async () => toolsPage(['10']));
    await tools.load();

    answer(async () => {
        throw new Error('Gateway timeout');
    });
    await tools.applyColumnFilters([{ id: 'mcp_server_id', value: '2' }]);
    assert.equal(tools.error, 'Gateway timeout');
    assert.equal(tools.loading, false);
    assert.equal(tools.stale, true, 'the rows of server 1 stay hidden behind the failure');
    assert.equal(tools.locked(tools.rows[0]), true);
    await tools.update(tools.rows[0], { active: false });
    assert.deepEqual(writes, []);
});

test('a silent refresh of the same server keeps its tools visible and editable', async () => {
    const section = fixture('1');
    const { tools, answer } = section;
    answer(async () => toolsPage(['10']));
    await tools.load();

    const pending = deferred();
    answer(() => pending.promise);
    const refreshed = tools.revalidate();
    await section.started;
    assert.equal(tools.loading, false, 'a routine refresh is silent');
    assert.equal(tools.stale, false);
    assert.equal(tools.locked(tools.rows[0]), false);
    pending.resolve(toolsPage(['10', '11']));
    await refreshed;
    assert.equal(tools.rows.length, 2);
    assert.equal(tools.stale, false);
});

test('a server with more tools than one page keeps the rest reachable through the next page', async () => {
    const hundred = Array.from({ length: 100 }, (_, index) => String(index + 1));
    const { tools, reads, answer } = fixture('1');
    answer(async () => toolsPage(hundred, 101));
    await tools.load();
    assert.equal(tools.rows.length, 100);
    assert.equal(tools.total, 101, 'the section reports every tool of the server, not just the page');
    assert.equal(new URL(reads[0]).searchParams.get('page[size]'), '100');

    answer(async () => toolsPage(['101'], 101));
    tools.pagination = { pageIndex: 1, pageSize: 100 };
    await tools.load();
    assert.equal(new URL(reads[1]).searchParams.get('page[number]'), '2');
    assert.equal(new URL(reads[1]).searchParams.get('filter[where][mcp_server_id]'), '1');
    assert.deepEqual(
        tools.rows.map((row) => row.id),
        ['101']
    );
    assert.equal(reads.length, 2, 'paging reads one page, never the whole section');
});

test('a selected server outside the loaded page is resolved by ID even with duplicate labels', () => {
    const selection = {
        selected: '42',
        rows: [AdminRowSchema.parse({ id: '1', server_label: 'Same label' })],
        loading: false,
        attempted: null
    };
    assert.equal(revealServerId(selection), '42');
    assert.equal(revealServerId({ ...selection, loading: true }), null);
    assert.equal(revealServerId({ ...selection, attempted: '42' }), null);
    assert.equal(revealServerId({ ...selection, selected: null }), null);
    assert.equal(revealServerId({ ...selection, selected: '1' }), null);
    assert.equal(revealServerId({ ...selection, selected: '99' }), '99');
});
