import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {RestApi} from '../../../resources/js/kernel/api/RestApi.js';
import {UriBuilder} from '../../../resources/js/kernel/api/UriBuilder.js';
import PasskeyBackupsSchema from '../../../resources/js/plugins/core/schemas/resources/passkey-backups.schema.js';

test('passkey backup is decoded and validated through the JSON:API resource accessor', async () => {
    const calls: Array<{path: string, options: RequestInit}> = [];
    const api = new RestApi(
        new UriBuilder('https://hawki.test'),
        async (path, options) => {
            calls.push({path, options});
            return {
                data: {
                    type: 'passkey-backups',
                    id: 'me',
                    attributes: {ciphertext: 'ciphertext', iv: 'iv', tag: 'tag'}
                }
            };
        },
        () => ({locale: 'en_US'} as any),
        resourceType => resourceType === 'passkey-backups' ? PasskeyBackupsSchema : undefined
    );

    const backup = await api.getResource('passkey-backups', 'me');

    assert.deepEqual(backup, {id: 'me', ciphertext: 'ciphertext', iv: 'iv', tag: 'tag'});
    assert.equal(calls[0].path, 'https://hawki.test/api/hawki/v1/passkey-backups/me');
    assert.equal(calls[0].options.method, 'GET');
});

test('passkey backup schema accepts only the current-user resource id', () => {
    assert.throws(
        () => PasskeyBackupsSchema.parse({id: 'another-user', ciphertext: 'ciphertext', iv: 'iv', tag: 'tag'}),
        /me/
    );
});
