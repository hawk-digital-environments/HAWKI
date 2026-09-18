import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {ChatTransport} from '../../../resources/js/plugins/core/modules/chat/transport/ChatTransport.js';
import {OldUiBridgeTransport} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/sending/transport/OldUiBridgeTransport.js';
import {createToolOrCapabilityWithStateFromTransferString as resolve, validatedToolSnapshot} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
import {catalog, model, translator} from './fixtures.js';

function fixture(requested = ['capability:web_search:search:{"limit":3}']) {
    const tools = catalog();
    const errors: string[] = [];
    const streams: any[] = [];
    const writes: any[] = [];
    const messages: any[] = [];
    let generations = 0;
    let refreshes = 0;
    let response: Promise<void> | undefined;
    let packet: any = {type: 'message', content: 'Answer'};
    const app: any = {
        connection: {isAuthenticated: true, userinfo: {id: 1, username: 'alice', name: 'Alice'}}, translator,
        uriBuilder: {storageFileUri: () => ''},
        // No plugins are loaded here, so no hook handler may run: apply hands
        // the initial value (e.g. the chatSend send descriptor) straight back.
        hooks: {apply: (_name: string, value: any) => value},
        refreshConnection: async () => {refreshes++;},
        stores: {get: (name: string) => name === 'ai-tools' ? tools : name === 'ai-models'
            ? {getOneById: () => model, getModelByIdOrFallback: () => model, getSystemModelByType: () => model, models: [model]}
            : {getPromptByType: () => ({prompt: 'System'})}},
        aiApi: {text: async () => 'Title', stream: async function* (request: any) {streams.push(request); yield packet;}}
    };
    const store: any = {
        active: {slug: 'chat-1', system_prompt: 'System'},
        create: async () => ({slug: 'chat-1'}),
        upload: async () => 'attachment-1',
        encryptText: async (value: string) => value,
        persistMessage: async (_slug: string, value: any) => {
            writes.push(value);
            return {message_id: String(writes.length), message_role: value.isAi ? 'assistant' : 'user', content: {text: value.__plainText}, metadata: value.metadata, model: value.model};
        },
        beginGeneration: () => {generations++;}, finishGeneration: () => {generations--;},
        appendMessage: (_slug: string, value: any) => messages.push(value),
        // The conversation↔assistant binding persistence (see ChatStore) has
        // no backing API in this fixture — the sends here address no assistant.
        updateAssistantHandle: async () => {},
        patchMessage: () => {}, removeCachedMessage: () => {}, replaceMessage: () => {},
        messagesFor: () => messages,
        findMessage: () => null,
        conversationName: () => 'Title', rename: async () => {}
    };
    const context: any = {
        message: 'Keep this draft', mode: {isEdit: false, isThread: false}, model: {current: model},
        modelParameters: {requestParameters: {temperature: 0.4}, get: () => 0.4}, systemPrompt: 'System',
        attachments: {list: []}, tools: {
            active: requested.map(value => resolve(value, tools)),
            reconcile: () => false,
            validatedActive: () => validatedToolSnapshot(requested, tools, model).map(value => resolve(value, tools))
        }
    };
    const options: any = {
        context, status: {failed: false, accepted: false, markAccepted() {this.accepted = true;}, hasFileUuid: () => false, setFileProgress: () => {}, setFileUuid: () => {}, getFileUuid: () => 'attachment-1'},
        setResponse: () => {}, setResponseFailed: (error: string) => errors.push(error),
        waitForResponse: (handler: any) => {response = handler({setAbortController: () => {}, triggerBodyChunk: () => {}, triggerReceived: () => {}, triggerError: (error: string) => errors.push(error)});}
    };
    const transport = new ChatTransport(app, store);
    return {app, tools, errors, streams, writes, context, options, store, transport,
        finish: async () => {await response;}, generations: () => generations, refreshes: () => refreshes,
        setPacket: (value: any) => {packet = value;}};
}

for (const mode of ['new', 'existing', 'thread']) {
    test(`${mode} send uses the same validated tool/settings snapshot for dispatch and persisted metadata`, async () => {
        const f = fixture();
        if (mode === 'new') f.store.active = null;
        if (mode === 'thread') f.context.mode = {isEdit: false, isThread: true, getState: () => ({threadId: '2'})};
        await f.transport.sendMessage(f.options);
        await f.finish();
        assert.deepEqual(f.errors, []);
        assert.equal(f.streams.length, 1);
        assert.deepEqual(f.streams[0].tools, ['capability:web_search:search:{"limit":3}']);
        assert.deepEqual(f.writes.at(-1).metadata.tools, f.streams[0].tools);
        assert.equal(f.streams[0].threadIndex, mode === 'thread' ? 2 : 0);
        assert.equal(f.generations(), 0);
    });
}

test('send-time removal aborts before persistence and keeps the draft', async () => {
    const f = fixture();
    f.context.tools.reconcile = () => {f.context.tools.active = []; return true;};
    await f.transport.sendMessage(f.options);
    assert.equal(f.context.message, 'Keep this draft');
    assert.deepEqual(f.errors, ['chat.tools.accessDenied']);
    assert.deepEqual(f.streams, []);
    assert.deepEqual(f.writes, []);
});

test('revocation during attachment upload aborts without persisting the message or invoking a provider', async () => {
    const f = fixture();
    f.context.attachments.list = [{name: 'example.txt', type: 'text/plain'}];
    f.store.upload = async () => {f.tools.tools = []; return 'attachment-1';};
    await f.transport.sendMessage(f.options);
    assert.equal(f.context.message, 'Keep this draft');
    assert.deepEqual(f.errors, ['chat.tools.accessDenied']);
    assert.deepEqual(f.streams, []);
    assert.deepEqual(f.writes, []);
});

test('selected tools block during refresh while tool-free chat can continue', async () => {
    const selected = fixture();
    selected.tools.authorizationState = 'refreshing';
    await selected.transport.sendMessage(selected.options);
    assert.deepEqual(selected.errors, ['chat.tools.authorizationRefreshing']);
    assert.deepEqual(selected.writes, []);
    const plain = fixture([]);
    plain.tools.authorizationState = 'refreshing';
    await plain.transport.sendMessage(plain.options);
    await plain.finish();
    assert.equal(plain.streams.length, 1);
    assert.deepEqual(plain.errors, []);
});

test('regeneration rejects revoked historical tools and never silently retries with a different choice', async () => {
    const f = fixture();
    f.tools.tools = [];
    await assert.rejects(f.transport.regenerateMessage('chat-1', {message_id: '2', model: model.model_id, metadata: {tools: ['capability:web_search:search']}} as any, null), /chat.tools.accessDenied/);
    assert.deepEqual(f.streams, []);
    assert.deepEqual(f.writes, []);
    assert.equal(f.generations(), 0);
});

test('regeneration uses the validated historical tool/settings snapshot', async () => {
    const f = fixture();
    await f.transport.regenerateMessage('chat-1', {message_id: '2', model: model.model_id, metadata: {tools: ['capability:web_search:search:{"limit":3}'], params: {temperature: 0.2}}} as any, null);
    assert.equal(f.streams.length, 1);
    assert.deepEqual(f.streams[0].tools, ['capability:web_search:search:{"limit":3}']);
    assert.deepEqual(f.writes.at(-1).metadata.tools, f.streams[0].tools);
});

for (const code of ['TOOL_ACCESS_DENIED', 'TOOL_UNAVAILABLE']) {
    test(`stream ${code} is translated and never retried`, async () => {
        const f = fixture();
        f.setPacket({type: 'error', code, content: 'Do not show server text'});
        await f.transport.sendMessage(f.options);
        await f.finish();
        assert.deepEqual(f.errors, [code === 'TOOL_ACCESS_DENIED' ? 'chat.tools.accessDenied' : 'chat.tools.unavailable']);
        assert.equal(f.streams.length, 1);
        assert.equal(f.refreshes(), code === 'TOOL_ACCESS_DENIED' ? 1 : 0);
        assert.equal(f.writes.length, 1);
    });
}

test('actor change while a send is pending cannot dispatch the previous actor request', async () => {
    const f = fixture();
    f.context.attachments.list = [{name: 'example.txt', type: 'text/plain'}];
    f.store.upload = async () => {f.app.connection.userinfo.id = 2; return 'attachment-1';};
    await f.transport.sendMessage(f.options);
    assert.equal(f.context.message, 'Keep this draft');
    assert.deepEqual(f.errors, ['chat.tools.accessDenied']);
    assert.deepEqual(f.streams, []);
});

test('the legacy transport rejects stale tool choices before forwarding and preserves its draft', async () => {
    const f = fixture();
    let forwarded = 0;
    const transport = new OldUiBridgeTransport({triggerSendMessage: async () => {forwarded++;}} as any, f.app);
    f.tools.tools = [];
    await transport.sendMessage(f.options);
    assert.equal(forwarded, 0);
    assert.deepEqual(f.errors, ['chat.tools.accessDenied']);
    assert.equal(f.context.message, 'Keep this draft');
});

test('HTTP tool error codes use translated messages without returning server prose or retrying', async () => {
    const {ApiTransportError} = await import('../../../resources/js/kernel/api/errors.js');
    for (const code of ['TOOL_ACCESS_DENIED', 'TOOL_UNAVAILABLE']) {
        const f = fixture();
        f.app.aiApi.stream = async function* () {
            throw new ApiTransportError(code === 'TOOL_ACCESS_DENIED' ? 403 : 422, [], {code, message: 'Server detail'}, 'Server detail');
        };
        await f.transport.sendMessage(f.options);
        await f.finish();
        assert.deepEqual(f.errors, [code === 'TOOL_ACCESS_DENIED' ? 'chat.tools.accessDenied' : 'chat.tools.unavailable']);
        assert.equal(f.writes.length, 1);
    }
});

test('real AiApi HTTP wrappers preserve denial codes and the client refreshes once without replay', async () => {
    const {ClientExtension} = await import('../../../resources/js/kernel/client/ClientExtension.svelte.js');
    const {EventExtension} = await import('../../../resources/js/kernel/events/EventExtension.js');
    const previous = {window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch};
    Object.assign(globalThis, {window: {location: {origin: 'https://hawki.test'}}, document: {cookie: '', querySelector: () => null}});
    try {
        for (const code of ['TOOL_ACCESS_DENIED', 'TOOL_UNAVAILABLE']) {
            const f = fixture();
            const client = new ClientExtension(new EventExtension().events);
            (client as any).connectionHandle.currentConnection = f.app.connection;
            client.ready(f.app);
            let refreshes = 0;
            let requests = 0;
            client.refreshConnection = async () => {refreshes++; return f.app.connection;};
            f.app.aiApi = client.client.aiApi;
            globalThis.fetch = async () => {requests++; return new Response(JSON.stringify({code, message: 'Server prose must stay hidden'}), {status: code === 'TOOL_ACCESS_DENIED' ? 403 : 422});};
            await f.transport.sendMessage(f.options);
            await f.finish();
            assert.deepEqual(f.errors, [code === 'TOOL_ACCESS_DENIED' ? 'chat.tools.accessDenied' : 'chat.tools.unavailable']);
            assert.equal(requests, 1);
            assert.equal(refreshes, code === 'TOOL_ACCESS_DENIED' ? 1 : 0);
        }
    } finally {Object.assign(globalThis, previous);}
});

for (const change of ['revocation', 'actor', 'model']) {
    test(`a message committed during ${change} is marked accepted and is never dispatched with stale choices`, async () => {
        const f = fixture();
        let commit!: () => void;
        const gate = new Promise<void>(resolve => {commit = resolve;});
        const persist = f.store.persistMessage;
        f.store.persistMessage = async (...args: any[]) => {await gate; return persist(...args);};
        const sending = f.transport.sendMessage(f.options);
        await new Promise(resolve => setImmediate(resolve));
        if (change === 'revocation') f.tools.tools = [];
        else if (change === 'actor') f.app.connection.userinfo.id = 2;
        else {
            const get = f.app.stores.get;
            f.app.stores.get = (name: string) => name === 'ai-models' ? {getOneById: () => null} : get(name);
        }
        commit();
        await sending;
        assert.equal(f.writes.length, 1);
        assert.equal(f.options.status.accepted, true);
        assert.deepEqual(f.errors, ['chat.tools.messageAccepted']);
        assert.deepEqual(f.streams, []);
        assert.equal(f.generations(), 0);
    });
}

async function legacySandbox(f: ReturnType<typeof fixture>, kind: 'chat' | 'room') {
    const {readFile} = await import('node:fs/promises');
    const vm = await import('node:vm');
    let submits = 0;
    const sandbox: any = {
        console, AbortController, TextDecoder, TextEncoder, Response, ReadableStream, setTimeout,
        window: {__: (key: string) => key, waitUntilReady: () => {},
            oldUiBridge: {bindAbortController: () => {}},
            userKeychain: {aiConvKey: {}, roomKeys: {'room-1': {roomKey: {}, aiKey: {}}}}},
        document: {querySelector: () => ({childElementCount: 1})},
        escapeHTML: (value: string) => value,
        encryptWithSymKey: async () => ({ciphertext: '', iv: '', tag: ''}),
        exportSymmetricKey: async () => new ArrayBuffer(0), arrayBufferToBase64: () => '',
        detectMentioning: (text: string) => ({filteredText: text}),
        uploadAttachmentQueue: async () => {},
        submitMessageToServer: async () => {submits++; return {content: {text: ''}};},
        addMessageToChatlog: () => ({dataset: {}}), scrollToLast: () => {},
        activeModule: kind === 'chat' ? 'chat' : 'groupchat'
    };
    vm.createContext(sandbox);
    for (const name of ['stream_functions.js', kind === 'chat' ? 'ai_chat_functions.js' : 'groupchat_functions.js']) {
        vm.runInContext(await readFile(new URL(`../../../public/js/${name}`, import.meta.url), 'utf8'), sandbox);
    }
    vm.runInContext(kind === 'chat' ? "activeConv = {slug: 'chat-1'};" : "activeRoom = {slug: 'room-1'};", sandbox);
    sandbox.createMessageLogForAI = () => [];
    f.context.type = kind === 'chat' ? 'aiConv' : 'room';
    f.context.containsAiHandle = true;
    f.context.mode.state = {is: 'default'};
    const transport = new OldUiBridgeTransport({triggerSendMessage: async (payload: any) => {
        if (kind === 'chat') await sandbox.sendMessageConv(payload);
        else await sandbox.onSendMessageToRoom(payload);
    }} as any, f.app);
    return {sandbox, transport, submits: () => submits};
}

for (const kind of ['chat', 'room'] as const) {
    test(`real legacy ${kind} upload handler rejects access revoked after bridge handoff`, async () => {
        const f = fixture();
        const legacy = await legacySandbox(f, kind);
        f.context.attachments.list = [{name: 'example.txt', type: 'text/plain'}];
        f.options.status.getFileUuid = () => null;
        f.options.status.clearFileIssue = () => {};
        legacy.sandbox.uploadAttachmentQueue = async () => {f.tools.tools = [];};
        await legacy.transport.sendMessage(f.options);
        assert.equal(legacy.submits(), 0);
        assert.deepEqual(f.errors, ['chat.tools.accessDenied']);
        assert.equal(f.options.status.accepted, false);
        assert.equal(f.context.message, 'Keep this draft');
    });

    test(`real legacy ${kind} encryption delay cannot dispatch another actor's request`, async () => {
        const f = fixture();
        const legacy = await legacySandbox(f, kind);
        legacy.sandbox.encryptWithSymKey = async () => {f.app.connection.userinfo.id = 2; return {ciphertext: '', iv: '', tag: ''};};
        await legacy.transport.sendMessage(f.options);
        assert.equal(legacy.submits(), 0);
        assert.deepEqual(f.errors, ['chat.tools.accessDenied']);
        assert.equal(f.options.status.accepted, false);
    });
}

test('legacy final dispatch validates the captured snapshot and translates stream errors with one refresh', async () => {
    const f = fixture();
    const legacy = await legacySandbox(f, 'chat');
    let handoff: any;
    const bridge = new OldUiBridgeTransport({triggerSendMessage: async (payload: any) => {handoff = payload;}} as any, f.app);
    await bridge.sendMessage(f.options);
    let requests = 0;
    let refreshes = 0;
    const packets: any[] = [];
    const attrs = {model: model.model_id, metadata: {tools: handoff.toolTransfers}, authorization: {
        ...handoff.authorization,
        refresh: () => {refreshes++;},
        request: async () => {requests++; return new Response(JSON.stringify({type: 'error', code: 'TOOL_ACCESS_DENIED', content: 'Server prose', isDone: true}) + '\n');}
    }};
    await legacy.sandbox.buildRequestObject(attrs, (packet: any) => {if (packet) packets.push(packet);});
    assert.equal(requests, 1);
    assert.equal(refreshes, 1);
    assert.equal(packets[0].content, 'chat.tools.accessDenied');
    f.tools.tools = [];
    await legacy.sandbox.buildRequestObject(attrs, () => {});
    assert.equal(requests, 1);
    assert.deepEqual(f.errors, ['chat.tools.accessDenied']);
});

for (const kind of ['chat', 'room'] as const) {
    test(`legacy ${kind} records acceptance when access changes during persistence`, async () => {
        const f = fixture();
        const legacy = await legacySandbox(f, kind);
        let writes = 0;
        legacy.sandbox.submitMessageToServer = async () => {writes++; f.tools.tools = []; return {content: {text: ''}};};
        await legacy.transport.sendMessage(f.options);
        assert.equal(writes, 1);
        assert.equal(f.options.status.accepted, true);
        assert.deepEqual(f.errors, ['chat.tools.messageAccepted']);
    });
}

test('legacy room regeneration revalidates after key export and preserves the original message', async () => {
    const {readFile} = await import('node:fs/promises');
    const vm = await import('node:vm');
    const f = fixture();
    const legacy = await legacySandbox(f, 'room');
    const message = {dataset: {rawMsg: 'Original response'}, closest: () => ({id: '0'})};
    legacy.sandbox.document.getElementById = () => message;
    vm.runInContext(await readFile(new URL('../../../public/js/message_functions.js', import.meta.url), 'utf8'), legacy.sandbox);
    legacy.sandbox.exportSymmetricKey = async () => {f.tools.tools = []; return new ArrayBuffer(0);};
    let requests = 0;
    f.app.restApi = {fetch: async () => {requests++; throw new Error('Must not dispatch');}};
    f.context.mode.state = {is: 'regen', messageId: '2'};
    await legacy.transport.sendMessage(f.options);
    await f.finish();
    assert.equal(requests, 0);
    assert.deepEqual(f.errors, ['chat.tools.accessDenied']);
    assert.equal(message.dataset.rawMsg, 'Original response');
});

test('legacy tool-free AI requests still reject a removed model after the composer draft clears', async () => {
    const f = fixture([]);
    f.context.type = 'room';
    f.context.containsAiHandle = true;
    let payload: any;
    const bridge = new OldUiBridgeTransport({triggerSendMessage: async (value: any) => {payload = value;}} as any, f.app);
    await bridge.sendMessage(f.options);
    f.context.containsAiHandle = false;
    const get = f.app.stores.get;
    f.app.stores.get = (name: string) => name === 'ai-models' ? {getOneById: () => null} : get(name);
    assert.equal(payload.authorization.validate(), false);
    assert.deepEqual(f.errors, ['chat.tools.unavailable']);
});

test('legacy HTTP denial passes through the real client and translates without replay or duplicate refresh', async () => {
    const {ClientExtension} = await import('../../../resources/js/kernel/client/ClientExtension.svelte.js');
    const {EventExtension} = await import('../../../resources/js/kernel/events/EventExtension.js');
    const previous = {window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch};
    Object.assign(globalThis, {window: {location: {origin: 'https://hawki.test'}}, document: {cookie: '', querySelector: () => null}});
    try {
        const f = fixture();
        const legacy = await legacySandbox(f, 'chat');
        const client = new ClientExtension(new EventExtension().events);
        (client as any).connectionHandle.currentConnection = f.app.connection;
        client.ready(f.app);
        let refreshes = 0;
        let requests = 0;
        client.refreshConnection = async () => {refreshes++; return f.app.connection;};
        f.app.restApi = client.client.restApi;
        globalThis.fetch = async () => {requests++; return new Response(JSON.stringify({code: 'TOOL_ACCESS_DENIED', message: 'Server prose'}), {status: 403});};
        let payload: any;
        await new OldUiBridgeTransport({triggerSendMessage: async (value: any) => {payload = value;}} as any, f.app).sendMessage(f.options);
        let error: Error | undefined;
        await legacy.sandbox.buildRequestObject({model: model.model_id, metadata: {tools: payload.toolTransfers}, authorization: payload.authorization}, () => {}, (value: Error) => {error = value;});
        assert.equal(error?.message, 'chat.tools.accessDenied');
        assert.equal(requests, 1);
        assert.equal(refreshes, 1);
    } finally {Object.assign(globalThis, previous);}
});
