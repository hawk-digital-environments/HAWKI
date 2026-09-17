import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { flushSync } from 'svelte';
import { RestApi } from '../../../resources/js/kernel/api/RestApi.js';
import { UriBuilder } from '../../../resources/js/kernel/api/UriBuilder.js';
import { AdminRowSchema } from '../../../resources/js/plugins/admin/schemas/admin-content.js';
import { ModelLookup } from '../../../resources/js/plugins/admin/forms/modelLookup.svelte.js';

function fixture(discover?: (url: string) => Promise<unknown>) {
    const pending: { url: string; resolve: (value: unknown) => void }[] = [];
    const restApi = new RestApi(
        new UriBuilder('https://hawki.test'),
        (url) => {
            if (url.includes('/actions/discover')) return (discover ?? (() => Promise.resolve({ models: [] })))(url);
            return new Promise((resolve) => pending.push({ url, resolve }));
        },
        () => {
            throw new Error('Connection not loaded');
        },
        () => AdminRowSchema
    );
    return { restApi, pending };
}

test('a pending inspection is dropped when the provider changes before it answers', async () => {
    const { restApi, pending } = fixture();
    const adopted: Record<string, unknown>[] = [];
    let providerId = $state<string>('a');
    let lookup!: ModelLookup;
    const stop = $effect.root(() => {
        lookup = new ModelLookup({
            restApi,
            providerId: () => providerId,
            modelId: () => 'shared-model',
            adopt: (values) => adopted.push(values)
        });
    });
    try {
        flushSync();
        const inspection = lookup.inspect('shared-model');
        assert.equal(lookup.inspecting, true);
        assert.ok(pending[0]?.url.includes('/admin-providers/a/actions/inspect'));
        providerId = 'b';
        flushSync();
        assert.equal(lookup.inspecting, false, 'switching providers cancels the pending inspection');
        pending[0].resolve({ model: { label: 'From provider A' } });
        await inspection;
        assert.deepEqual(adopted, [], "provider A's metadata never reaches the form for provider B");
        assert.equal(lookup.inspected, null);
    } finally {
        stop();
    }
});

test('an inspection for the current provider and model id is adopted', async () => {
    const { restApi, pending } = fixture();
    const adopted: Record<string, unknown>[] = [];
    let lookup!: ModelLookup;
    const stop = $effect.root(() => {
        lookup = new ModelLookup({
            restApi,
            providerId: () => 'a',
            modelId: () => 'shared-model',
            adopt: (values) => adopted.push(values)
        });
    });
    try {
        flushSync();
        const inspection = lookup.inspect('shared-model');
        pending[0].resolve({ model: { label: 'From provider A' } });
        await inspection;
        assert.deepEqual(adopted, [{ label: 'From provider A' }]);
        assert.equal(lookup.inspected, 'done');
        assert.equal(lookup.inspecting, false);
    } finally {
        stop();
    }
});

test('the picker keeps a list while discovery runs and after it fails', async () => {
    let rejectDiscovery!: (reason: unknown) => void;
    const { restApi } = fixture(() => new Promise((_, reject) => (rejectDiscovery = reject)));
    let lookup!: ModelLookup;
    const stop = $effect.root(() => {
        lookup = new ModelLookup({
            restApi,
            providerId: () => 'a',
            modelId: () => '',
            adopt: () => {}
        });
    });
    try {
        flushSync();
        assert.equal(lookup.suggesting, true);
        assert.deepEqual(lookup.pickerItems, [], 'a loading provider still offers a list to render');
        rejectDiscovery(new Error('provider unreachable'));
        await new Promise((resolve) => setTimeout(resolve, 0));
        flushSync();
        assert.equal(lookup.failed, true);
        assert.deepEqual(lookup.pickerItems, [], 'a failed discovery still offers a list to render');
    } finally {
        stop();
    }
});
