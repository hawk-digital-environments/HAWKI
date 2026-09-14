import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { RestApi } from '../../../resources/js/kernel/api/RestApi.js';
import { UriBuilder } from '../../../resources/js/kernel/api/UriBuilder.js';
import { AdminRowSchema } from '../../../resources/js/plugins/admin/schemas/admin-content.js';

test('resource helpers send JSON:API documents and conditional mutations for admin records', async () => {
    const calls: { url: string; options: RequestInit }[] = [];
    const api = new RestApi(
        new UriBuilder('https://hawki.test'),
        async (url, options) => {
            calls.push({ url, options });
            if (options.method === 'DELETE') return undefined;
            return {
                data: { type: 'admin-roles', id: '17', attributes: { name: 'Saved' }, meta: { version: 'new-version' } }
            };
        },
        () => {
            throw new Error('No connection');
        },
        () => AdminRowSchema
    );
    const created = await api.createResource('admin-roles', { name: 'Created' });
    const updated = await api.updateResource(
        'admin-roles',
        '17',
        { name: 'Updated' },
        { headers: { 'If-Match': '"version"' } }
    );
    await api.deleteResource('admin-roles', '17', { headers: { 'If-Match': '"new-version"' } });
    assert.equal(created.id, '17');
    assert.equal(updated.name, 'Saved');
    assert.equal(updated._meta.version, 'new-version');
    assert.deepEqual(
        calls.map(({ url, options }) => [url, options.method, options.body && JSON.parse(String(options.body))]),
        [
            [
                'https://hawki.test/api/hawki/v1/admin-roles',
                'POST',
                { data: { type: 'admin-roles', attributes: { name: 'Created' } } }
            ],
            [
                'https://hawki.test/api/hawki/v1/admin-roles/17',
                'PATCH',
                { data: { type: 'admin-roles', id: '17', attributes: { name: 'Updated' } } }
            ],
            ['https://hawki.test/api/hawki/v1/admin-roles/17', 'DELETE', undefined]
        ]
    );
    assert.equal(new Headers(calls[0].options.headers).get('Content-Type'), 'application/vnd.api+json');
    assert.equal(new Headers(calls[1].options.headers).get('If-Match'), '"version"');
    assert.equal(new Headers(calls[2].options.headers).get('If-Match'), '"new-version"');
});

test('resource URLs encode identifiers without changing their document identity', async () => {
    const calls: { url: string; options: RequestInit }[] = [];
    const api = new RestApi(
        new UriBuilder('https://hawki.test'),
        async (url, options) => {
            calls.push({ url, options });
            return { data: { type: 'admin-settings', id: 'feature/key', attributes: { value: true } } };
        },
        () => {
            throw new Error('No connection');
        },
        () => AdminRowSchema
    );
    await api.getResource('admin-settings', 'feature/key');
    await api.updateResource('admin-settings', 'feature/key', { value: true });
    await api.deleteResource('admin-settings', 'feature/key');
    assert.ok(calls.every(({ url }) => url.endsWith('/admin-settings/feature%2Fkey')));
    assert.equal(JSON.parse(String(calls[1].options.body)).data.id, 'feature/key');
});
