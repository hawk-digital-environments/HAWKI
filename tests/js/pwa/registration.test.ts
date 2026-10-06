import assert from 'node:assert/strict';
import {afterEach, test} from 'node:test';
import {registerPwa} from '../../../resources/js/kernel/pwa/PwaExtension.js';

const originals = new Map(['window', 'navigator', 'document'].map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));

afterEach(() => {
    for (const [key, descriptor] of originals) {
        if (descriptor) {
            Object.defineProperty(globalThis, key, descriptor);
        } else {
            Reflect.deleteProperty(globalThis, key);
        }
    }
});

function browser({secure = true, supported = true, manifest = true, fail = false} = {}) {
    const calls: unknown[][] = [];
    Object.defineProperty(globalThis, 'window', {configurable: true, value: {isSecureContext: secure}});
    Object.defineProperty(globalThis, 'document', {
        configurable: true,
        value: {querySelector: (selector: string) => selector === 'link[rel="manifest"]' && manifest ? {} : null}
    });
    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: supported ? {
            serviceWorker: {
                register: async (...args: unknown[]) => {
                    calls.push(args);
                    if (fail) throw new Error('Registration blocked');
                }
            }
        } : {}
    });
    return calls;
}

test('registers the same-origin worker for the Svelte frontend with fresh update checks', async () => {
    const calls = browser();
    await registerPwa();
    assert.deepEqual(calls, [['/sw.js', {scope: '/new', updateViaCache: 'none'}]]);
});

for (const [name, options] of [
    ['insecure contexts', {secure: false}],
    ['unsupported browsers', {supported: false}],
    ['legacy pages without a manifest', {manifest: false}]
] as const) {
    test(`does not register in ${name}`, async () => {
        const calls = browser(options);
        await registerPwa();
        assert.equal(calls.length, 0);
    });
}

test('registration failures do not reject app startup', async (context) => {
    browser({fail: true});
    const warning = context.mock.method(console, 'warn', () => {});
    await assert.doesNotReject(registerPwa());
    assert.equal(warning.mock.callCount(), 1);
});
