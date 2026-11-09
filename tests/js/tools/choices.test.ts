import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import {
    createToolOrCapabilityWithStateFromTransferString as resolve,
    validatedToolSnapshot
} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
import { capability, catalog, foreignToolModel, model, noToolCallingModel, tool } from './fixtures.js';

test('a capability transfer string round-trips with its settings', () => {
    const value = 'capability:web_search:search:{"query":"https://example.test","limit":3}';
    assert.equal(resolve(value, catalog())!.toTransferString(), value);
});

test('a snapshot rejects every stale selection', () => {
    const store = catalog();
    assert.deepEqual(validatedToolSnapshot(['capability:web_search:search'], store, model), [
        'capability:web_search:search'
    ]);
    store.tools = [];
    assert.throws(() => validatedToolSnapshot(['documents'], store, model), /TOOL_ACCESS_DENIED/);
    assert.deepEqual(validatedToolSnapshot([], store, model), []);
});

test('a model without tool calling cannot re-run any snapshot that uses tools', () => {
    const store = catalog();
    // A capability needs `tool_calling === true`; a plain tool is blocked by an
    // explicit `false` (an absent flag leaves plain tools usable).
    const flaggedOff = { ...noToolCallingModel, settings: { tool_calling: false } };
    for (const candidate of [noToolCallingModel, flaggedOff]) {
        assert.throws(
            () => validatedToolSnapshot(['capability:web_search:search'], store, candidate),
            /TOOL_UNAVAILABLE/
        );
        // Tool-free snapshots stay legal on any model.
        assert.deepEqual(validatedToolSnapshot([], store, candidate), []);
    }
    assert.throws(() => validatedToolSnapshot(['documents'], store, flaggedOff), /TOOL_UNAVAILABLE/);
    assert.deepEqual(validatedToolSnapshot(['documents'], store, noToolCallingModel), ['documents']);
});

test('a model the tool is not assigned to cannot re-run the snapshot', () => {
    const store = catalog();
    assert.throws(
        () => validatedToolSnapshot(['capability:web_search:search'], store, foreignToolModel),
        /TOOL_UNAVAILABLE/
    );
    assert.throws(() => validatedToolSnapshot(['documents'], store, foreignToolModel), /TOOL_UNAVAILABLE/);
    assert.deepEqual(validatedToolSnapshot(['capability:web_search:search'], store, model), [
        'capability:web_search:search'
    ]);
});

test('an offline assigned tool cannot re-run the snapshot', () => {
    const store = catalog([{ ...tool, status: 'offline' }]);
    assert.throws(() => validatedToolSnapshot(['capability:web_search:auto'], store, model), /TOOL_UNAVAILABLE/);
    assert.throws(() => validatedToolSnapshot(['capability:web_search:search'], store, model), /TOOL_UNAVAILABLE/);
});

test('auto availability reports offline for this model even if another model has an online variant', async () => {
    const { toolAvailabilityFor } = await import('../../../resources/js/plugins/core/stores/aiToolStoreData.js');
    const store = catalog(
        [
            { ...tool, status: 'offline' },
            { ...tool, id: '22', name: 'other-search' }
        ],
        [{ ...capability }]
    );
    const selected = resolve('capability:web_search:auto', store)!;
    assert.equal(toolAvailabilityFor(selected, { ...model, tool_ids: [11] }), 'offline');
    assert.equal(toolAvailabilityFor(selected, { ...model, tool_ids: [22] }), 'available');
    assert.equal(toolAvailabilityFor(selected, { ...model, tool_ids: [] }), 'model-incompatible');
    assert.equal(toolAvailabilityFor(selected, noToolCallingModel), 'model-incompatible');
    const explicit = resolve('capability:web_search:search', store)!;
    assert.equal(toolAvailabilityFor(explicit, model), 'offline');
});
