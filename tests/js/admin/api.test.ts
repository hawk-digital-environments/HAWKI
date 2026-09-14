import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { RestApi } from '../../../resources/js/kernel/api/RestApi.js';
import { UriBuilder } from '../../../resources/js/kernel/api/UriBuilder.js';
import { createAdmin, updateAdmin, deleteAdmin, readAdmin, runAdmin } from '../../../resources/js/plugins/admin/api.js';

function client() {
    const calls: { url: string; options: RequestInit }[] = [];
    const uriBuilder = new UriBuilder('https://hawki.test');
    const restApi = new RestApi(
        uriBuilder,
        async (url, options) => {
            calls.push({ url, options });
            if (options.method === 'GET') return { content: { rows: [], columns: [] } };
            return { id: '17' };
        },
        () => {
            throw new Error('Connection not loaded');
        },
        () => undefined
    );
    return { restApi, uriBuilder, calls };
}

test('admin writes address the resource and record with HTTP methods and keep versions', async () => {
    const api = client();
    await createAdmin(api, 'providers', { name: 'Provider' });
    await updateAdmin(api, 'roles', { id: '17', _version: 'version' }, { name: 'Role' });
    await deleteAdmin(api, 'settings', { id: 'feature/key', _version: 'version' });
    assert.deepEqual(
        api.calls.map(({ url, options }) => [url, options.method, JSON.parse(String(options.body))]),
        [
            ['https://hawki.test/api/hawki/v1/admin/providers', 'POST', { values: { name: 'Provider' } }],
            [
                'https://hawki.test/api/hawki/v1/admin/roles/17',
                'PATCH',
                { version: 'version', values: { name: 'Role' } }
            ],
            ['https://hawki.test/api/hawki/v1/admin/settings/feature%2Fkey', 'DELETE', { version: 'version' }]
        ]
    );
});

test('admin reads preserve filters, cancellation and response validation', async () => {
    const api = client();
    const signal = new AbortController().signal;
    const content = await readAdmin(api, 'models', { filter: { page: 2, search: 'a & b' } }, signal);
    const url = new URL(api.calls[0].url);
    assert.equal(url.pathname, '/api/hawki/v1/admin/models');
    assert.equal(url.searchParams.get('filter[page]'), '2');
    assert.equal(url.searchParams.get('filter[search]'), 'a & b');
    assert.equal(api.calls[0].options.signal, signal);
    assert.deepEqual(content.rows, []);
    assert.equal(content.create, false);
});

test('operational actions have explicit resource and record paths', async () => {
    const api = client();
    await runAdmin(api, 'providers', 'discover', '17');
    await runAdmin(api, 'providers', 'import');
    await runAdmin(api, 'users', 'tokens', '19');
    await runAdmin(api, 'providers', 'inspect', '17', { model_id: 'gpt-4o' });
    assert.deepEqual(
        api.calls.map(({ url, options }) => [url, options.method, options.body]),
        [
            ['https://hawki.test/api/hawki/v1/admin/providers/17/actions/discover', 'POST', undefined],
            ['https://hawki.test/api/hawki/v1/admin/providers/actions/import', 'POST', undefined],
            ['https://hawki.test/api/hawki/v1/admin/users/19/actions/tokens', 'GET', undefined],
            [
                'https://hawki.test/api/hawki/v1/admin/providers/17/actions/inspect',
                'POST',
                JSON.stringify({ model_id: 'gpt-4o' })
            ]
        ]
    );
});
