import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {flushSync} from 'svelte';
import {AiToolStore} from '../../../resources/js/plugins/core/stores/AiToolStore.svelte.js';
import {AiModelStore} from '../../../resources/js/plugins/core/stores/AiModelStore.svelte.js';
import {ToolSlice} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/ToolSlice.svelte.js';
import {EventExtension} from '../../../resources/js/kernel/events/EventExtension.js';
import {capability, catalog, deferred, model, standalone, tool, translator} from './fixtures.js';

function appFixture() {
    const events = new EventExtension().events;
    const models = new AiModelStore();
    const tools = new AiToolStore();
    const responses: Record<string, unknown[]> = {'ai-tools': [tool, standalone], 'ai-tool-capabilities': [capability], 'ai-models': [model], 'system-models': [], 'ai-model-flags': []};
    const calls: string[] = [];
    const app: any = {connection: {isAuthenticated: true, userinfo: {id: 1}}, events, localization: {translator},
        stores: {get: (name: string) => name === 'ai-models' ? models : tools},
        restApi: {getResourceCollection: async (name: string) => {calls.push(name); return responses[name];}}};
    models.ready(app);
    tools.ready(app);
    return {app, models, tools, responses, calls};
}

test('successful refresh reloads model and tool rules even when permission strings are unchanged', async () => {
    const f = appFixture();
    await f.models.loadData(f.app);
    await f.tools.loadData(f.app);
    f.responses['ai-tools'] = [];
    f.responses['ai-tool-capabilities'] = [];
    await f.app.events.async.triggerVoid('connectionRefreshStarted');
    assert.equal(f.tools.authorizationState, 'refreshing');
    await f.app.events.async.triggerVoid('connectionRefreshed', f.app.connection);
    assert.equal(f.tools.authorizationState, 'ready');
    assert.deepEqual(f.tools.tools, []);
    assert.deepEqual(f.calls.slice(-5), ['ai-models', 'system-models', 'ai-model-flags', 'ai-tools', 'ai-tool-capabilities']);
});

for (const Store of [AiToolStore, AiModelStore]) {
    test(`${Store.name} discards late reads after session loss and after an actor switch`, async () => {
        const f = appFixture();
        const store = Store === AiToolStore ? f.tools : f.models;
        const pending = deferred<any[]>();
        f.app.restApi.getResourceCollection = () => pending.promise;
        const request = store.loadData(f.app);
        f.app.events.sync.trigger('sessionLost');
        pending.resolve([]);
        await request;
        assert.equal(store.authorizationState, 'unknown');
        assert.deepEqual(store instanceof AiToolStore ? store.tools : store.models, []);

        const old = deferred<any[]>();
        f.app.restApi.getResourceCollection = () => old.promise;
        const stale = store.loadData(f.app);
        f.app.connection = {isAuthenticated: true, userinfo: {id: 2}};
        old.resolve([]);
        await stale;
        assert.notEqual(store.authorizationState, 'ready');
        f.app.connection = {isAuthenticated: false};
        await store.loadData(f.app);
        assert.equal(store.authorizationState, 'unknown');
    });
}

test('catalog revocation clears active and disabled selections once, and checkpoints cannot restore them', () => {
    const state = $state(catalog());
    let notices = 0;
    let slice!: ToolSlice;
    const dispose = $effect.root(() => {slice = new ToolSlice({current: model} as any, state, () => notices++);});
    try {
        flushSync();
        slice.setFromTransferString('capability:web_search:search:{"limit":3}');
        slice.setFromTransferString('documents');
        slice.disable(slice.active.find(value => value.name === 'documents')!);
        const checkpoint = slice.createCheckpoint();
        state.tools = [];
        flushSync();
        assert.equal(slice.all.length, 0);
        assert.equal(notices, 1);
        slice.restoreCheckpoint(checkpoint);
        assert.equal(slice.all.length, 0);
        assert.equal(notices, 2);
    } finally {dispose();}
});

test('reconciliation rebuilds wrappers and retains settings through offline and model conflicts', () => {
    const state = $state(catalog());
    const selectedModel = $state({current: model});
    let slice!: ToolSlice;
    const dispose = $effect.root(() => {slice = new ToolSlice(selectedModel as any, state);});
    try {
        flushSync();
        slice.setFromTransferString('capability:web_search:search:{"limit":3}');
        state.tools = catalog([{...tool, status: 'offline'}, standalone]).tools;
        flushSync();
        assert.equal(slice.active[0].isAvailableFor(model), false);
        assert.deepEqual(slice.active[0].toolSettings, {limit: 3});
        assert.throws(() => slice.validatedActive(), /TOOL_UNAVAILABLE/);
        state.tools = catalog().tools;
        selectedModel.current = {...model, tool_ids: []};
        flushSync();
        assert.equal(slice.active.length, 1);
        assert.equal(slice.active[0].isAvailableFor(selectedModel.current), false);
        selectedModel.current = model;
        flushSync();
        assert.equal(slice.validatedActive()[0].toTransferString(), 'capability:web_search:search:{"limit":3}');
    } finally {dispose();}
});

test('newer catalog loads win over older responses', async () => {
    const f = appFixture();
    const api = f.app.restApi.getResourceCollection;
    const old = deferred<any[]>();
    f.app.restApi.getResourceCollection = () => old.promise;
    const stale = f.tools.loadData(f.app);
    f.app.restApi.getResourceCollection = api;
    f.responses['ai-tools'] = [standalone];
    f.responses['ai-tool-capabilities'] = [];
    await f.tools.loadData(f.app);
    old.resolve([tool]);
    await stale;
    assert.deepEqual(f.tools.tools.map(value => value.name), ['documents']);
    assert.equal(f.tools.authorizationState, 'ready');
});

test('restoring a composer checkpoint preserves its draft and parameters while discarding revoked tools', async () => {
    const {ComposerContext} = await import('../../../resources/js/plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js');
    const {ContextCheckpointer} = await import('../../../resources/js/plugins/core/modules/chat/components/composer/contexts/utils/ContextCheckpointer.js');
    const state = $state(catalog());
    const checkpointer = new ContextCheckpointer();
    let slice!: ToolSlice;
    const dispose = $effect.root(() => {slice = new ToolSlice({current: model} as any, state);});
    const checkpointPart: any = {createCheckpoint: () => ({}), restoreCheckpoint: () => {}};
    let restoredParameters: unknown;
    const parameters: any = {createCheckpoint: () => ({temperature: 0.3}), restoreCheckpoint: (value: unknown) => {restoredParameters = value;}};
    const context = new ComposerContext('aiConv', checkpointPart, checkpointPart, parameters, checkpointPart, slice,
        {} as any, {} as any, checkpointer, {} as any, 'System', () => {}, async value => value, function* () {});
    try {
        flushSync();
        context.message = 'Saved draft';
        slice.setFromTransferString('documents');
        checkpointer.createCheckpoint();
        context.message = 'Editing an old message';
        state.tools = [];
        flushSync();
        checkpointer.restoreCheckpoint();
        assert.equal(context.message, 'Saved draft');
        assert.equal(context.systemPrompt, 'System');
        assert.deepEqual(restoredParameters, {temperature: 0.3});
        assert.equal(slice.all.length, 0);
    } finally {dispose();}
});

test('a tool store read before bootstrap updates when its first authorized catalog arrives', async () => {
    const store = new AiToolStore();
    assert.deepEqual(store.tools, []);
    const f = appFixture();
    await store.loadData(f.app);
    assert.equal(store.tools.length, 2);
});

test('an unassigned default model falls back to the available catalog and reads refreshed model settings', async () => {
    const {ModelSlice} = await import('../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/ModelSlice.svelte.js');
    const f = appFixture();
    await f.models.loadData(f.app);
    const slice = new ModelSlice(f.models, () => ({} as any), () => {});
    assert.equal(slice.current.model_id, model.model_id);
    f.responses['ai-models'] = [{...model, tool_ids: []}];
    await f.models.loadData(f.app);
    assert.deepEqual(slice.current.tool_ids, []);
});

test('a failed refresh retains selections and settings but blocks sending until catalogs recover', async () => {
    const f = appFixture();
    await f.tools.loadData(f.app);
    let slice!: ToolSlice;
    const dispose = $effect.root(() => {slice = new ToolSlice({current: model} as any, f.tools);});
    try {
        flushSync();
        slice.setFromTransferString('capability:web_search:search:{"limit":3}');
        const checkpoint = slice.createCheckpoint();
        f.app.restApi.getResourceCollection = async () => {throw new Error('Network unavailable');};
        await assert.rejects(f.tools.loadData(f.app));
        flushSync();
        assert.equal(f.tools.authorizationState, 'unknown');
        assert.equal(slice.active.length, 1);
        assert.equal(slice.authorizationPending, true);
        slice.restoreCheckpoint(checkpoint);
        assert.deepEqual(slice.active[0].toolSettings, {limit: 3});
        assert.throws(() => slice.validatedActive(), /TOOL_AUTHORIZATION_REFRESHING/);
        f.app.events.sync.trigger('sessionLost');
        flushSync();
        assert.equal(slice.all.length, 0);
        assert.deepEqual(f.tools.tools, []);
    } finally {dispose();}
});

test('offline tool issues and send guidance remain distinct from model incompatibility', async () => {
    const {ModelUsageSlice} = await import('../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/ModelUsageSlice.svelte.js');
    const {GuardSlice} = await import('../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/GuardSlice.svelte.js');
    const {createToolOrCapabilityWithStateFromTransferString: resolve} = await import('../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js');
    const store = catalog([{...tool, status: 'offline'}], [{...capability, native_model_ids: []}]);
    const selectedModel = $state({current: model});
    const tools: any = {active: [resolve('capability:web_search:search', store)], authorizationPending: false};
    const usage = new ModelUsageSlice({models: [model]} as any, selectedModel as any, tools,
        {list: [], hasImages: false} as any, {showsAiUiElements: true, disablesFeature: () => false} as any);
    const guard = new GuardSlice(() => ({hasWriteAccess: true, messageWithoutHandles: 'Draft', tools, modelUsage: usage} as any));
    assert.deepEqual(usage.issues.map(issue => issue.type), ['offline_tools']);
    assert.equal(guard.cannotSendReason, 'chat.tools.offline');
    assert.equal(guard.canSend, false);
    selectedModel.current = {...model, tool_ids: []};
    assert.deepEqual(usage.issues.map(issue => issue.type), ['missing_tools']);
    assert.equal(guard.cannotSendReason, 'chat.composer.actions.invalidModelTooltip');
});
