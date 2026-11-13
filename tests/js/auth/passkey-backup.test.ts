import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {RestApi} from '../../../resources/js/kernel/api/RestApi.js';
import {UriBuilder} from '../../../resources/js/kernel/api/UriBuilder.js';
import {type ApiTransport, type ApiTransportOptions} from '../../../resources/js/kernel/api/transport.js';
import PasskeyBackupsSchema from '../../../resources/js/plugins/core/schemas/resources/passkey-backups.schema.js';

test('passkey backup is decoded and validated through the JSON:API resource accessor', async () => {
    const calls: Array<{path: string, options?: ApiTransportOptions}> = [];
    // A JSON-only transport stub; the cast bridges to the stream overload the test never uses.
    const transport = async (path: string, options?: ApiTransportOptions): Promise<unknown> => {
        calls.push({path, options});
        return {
            data: {
                type: 'passkey-backups',
                id: 'me',
                attributes: {ciphertext: 'ciphertext', iv: 'iv', tag: 'tag'}
            }
        };
    };
    const api = new RestApi(
        new UriBuilder('https://hawki.test'),
        transport as unknown as ApiTransport,
        () => ({locale: 'en_US'} as any),
        resourceType => resourceType === 'passkey-backups' ? PasskeyBackupsSchema : undefined
    );

    const backup = await api.getResource('passkey-backups', 'me');

    assert.deepEqual(backup, {id: 'me', ciphertext: 'ciphertext', iv: 'iv', tag: 'tag'});
    assert.equal(calls[0].path, 'https://hawki.test/api/hawki/v1/passkey-backups/me');
    assert.equal(calls[0].options?.method, 'GET');
});

test('passkey backup schema accepts only the current-user resource id', () => {
    assert.throws(
        () => PasskeyBackupsSchema.parse({id: 'another-user', ciphertext: 'ciphertext', iv: 'iv', tag: 'tag'}),
        /me/
    );
});
