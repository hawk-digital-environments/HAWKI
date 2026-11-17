import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {createDefaultTransport} from '../../../resources/js/kernel/api/transport.js';
import {ApiTransportError} from '../../../resources/js/kernel/api/errors.js';

test('transport exposes error codes, reads rotated CSRF cookies and reports session rejection', async () => {
    const previousFetch = globalThis.fetch;
    const headers: Headers[] = [];
    const rejected: number[] = [];
    Object.assign(globalThis, {document: {cookie: 'XSRF-TOKEN=first%3D', querySelector: () => null}});
    globalThis.fetch = async (_url, options) => {
        headers.push(new Headers(options?.headers));
        const status = headers.length === 1 ? 401 : 419;
        return new Response(JSON.stringify({errors: [{code: 'invalid_credentials', detail: 'Invalid login'}, {code: 'other'}]}), {status});
    };
    try {
        const transport = createDefaultTransport(status => rejected.push(status));
        await assert.rejects(transport('/login', {method: 'POST'}), error => error instanceof ApiTransportError && error.code === 'invalid_credentials');
        document.cookie = 'XSRF-TOKEN=rotated%3D';
        await assert.rejects(transport('/login', {method: 'POST'}));
        assert.equal(headers[0].get('X-XSRF-TOKEN'), 'first=');
        assert.equal(headers[1].get('X-XSRF-TOKEN'), 'rotated=');
        assert.deepEqual(rejected, [401, 419]);
    } finally {
        globalThis.fetch = previousFetch;
    }
});
