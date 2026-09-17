import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import CapabilitySchema from '../../../resources/js/plugins/core/schemas/resources/ai-tools-capabilities.schema.js';
import {createToolOrCapabilityWithStateFromTransferString as resolve, validatedToolSnapshot} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
import {capability, catalog, model, tool} from './fixtures.js';

test('missing native grants deny native execution even when MCP and model capability are authorized', () => {
    const {native_model_ids: _, ...olderPayload} = capability;
    const parsed = CapabilitySchema.parse(olderPayload);
    assert.deepEqual(parsed.native_model_ids, []);
    const store = catalog([tool], [parsed]);
    assert.equal(resolve('capability:web_search:native', store)!.isAvailableFor(model), false);
    assert.equal(resolve('capability:web_search:auto', store)!.isAvailableFor(model), true);
    assert.equal(resolve('capability:web_search:native', catalog())!.isAvailableFor(model), true);
    assert.equal(resolve('capability:web_search:native', catalog())!.isAvailableFor({...model, id: '8'}), false);
});

test('unknown inner tools never fall back to auto and settings must be objects', () => {
    assert.equal(resolve('capability:web_search:revoked', catalog()), null);
    assert.equal(resolve('capability:web_search:', catalog()), null);
    assert.equal(resolve('capability:web_search:search:[]', catalog()), null);
    assert.equal(resolve('capability:web_search:search:null', catalog()), null);
    const value = 'capability:web_search:search:{"query":"https://example.test","limit":3}';
    assert.equal(resolve(value, catalog())!.toTransferString(), value);
});

test('auto needs an online assigned tool or an explicitly authorized native model', () => {
    const store = catalog([{...tool, status: 'offline'}], [{...capability, native_model_ids: []}]);
    const selected = resolve('capability:web_search:auto', store)!;
    assert.equal(selected.isAvailableFor(model), false);
    assert.equal(selected.isAvailableFor(model, true), true);
    assert.throws(() => validatedToolSnapshot(['capability:web_search:auto'], store, model), /TOOL_UNAVAILABLE/);
});

test('a send snapshot rejects every stale selection and permits tool-free chat during refresh', () => {
    const store = catalog();
    assert.deepEqual(validatedToolSnapshot(['capability:web_search:search'], store, model), ['capability:web_search:search']);
    store.authorizationState = 'refreshing';
    assert.throws(() => validatedToolSnapshot(['documents'], store, model), /TOOL_AUTHORIZATION_REFRESHING/);
    assert.deepEqual(validatedToolSnapshot([], store, model), []);
    store.authorizationState = 'ready';
    store.tools = [];
    assert.throws(() => validatedToolSnapshot(['documents'], store, model), /TOOL_ACCESS_DENIED/);
});

test('auto availability reports offline for this model even if another model has an online variant', async () => {
    const {toolAvailabilityFor} = await import('../../../resources/js/plugins/core/stores/aiToolStoreData.js');
    const store = catalog([{...tool, status: 'offline'}, {...tool, id: '22', name: 'other-search'}], [{...capability, native_model_ids: []}]);
    const selected = resolve('capability:web_search:auto', store)!;
    assert.equal(toolAvailabilityFor(selected, {...model, tool_ids: [11]}), 'offline');
    assert.equal(toolAvailabilityFor(selected, {...model, tool_ids: [22]}), 'available');
    assert.equal(toolAvailabilityFor(selected, {...model, tool_ids: []}), 'model-incompatible');
    const explicit = resolve('capability:web_search:search', store)!;
    assert.equal(toolAvailabilityFor(explicit, model), 'offline');
});
