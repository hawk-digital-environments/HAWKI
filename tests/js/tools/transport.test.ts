import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import {
    ChatTransport,
    MESSAGE_HISTORY_CAP
} from '../../../resources/js/plugins/core/modules/chat/transport/ChatTransport.js';
import {
    createToolOrCapabilityWithStateFromTransferString as resolve,
    validatedToolSnapshot
} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
import {
    catalog,
    fallbackModel,
    foreignToolModel,
    model,
    modelStoreFor,
    noToolCallingModel,
    translator
} from './fixtures.js';

/** Transport harness: no hook handlers are registered, so `hooks.apply` hands
 *  every initial value straight back — the plain, user-configured run. */
function fixture(requested = ['capability:web_search:search:{"limit":3}']) {
    const tools = catalog();
    const errors: string[] = [];
    const streams: any[] = [];
    const writes: any[] = [];
    const messages: any[] = [];
    let generations = 0;
    let response: Promise<void> | undefined;
    let packet: any = { type: 'message', content: 'Answer' };
    const models = modelStoreFor([model, noToolCallingModel, foreignToolModel, fallbackModel], fallbackModel);
    const app: any = {
        connection: { isAuthenticated: true, userinfo: { username: 'alice', name: 'Alice' } },
        translator,
        uriBuilder: { storageFileUri: () => '' },
        hooks: { apply: (_name: string, value: any) => value },
        stores: {
            get: (name: string) =>
                name === 'ai-tools' ? tools
                : name === 'ai-models' ? models
                : { getPromptByType: () => ({ prompt: 'System' }) }
        },
        aiApi: {
            text: async () => 'Title',
            stream: async function* (request: any) {
                streams.push(request);
                yield packet;
            }
        }
    };
    const store: any = {
        active: { slug: 'chat-1', system_prompt: 'System' },
        create: async () => ({ slug: 'chat-1' }),
        upload: async () => 'attachment-1',
        encryptText: async (value: string) => value,
        persistMessage: async (_slug: string, value: any) => {
            writes.push(value);
            return {
                message_id: value.message_id ?? String(writes.length),
                message_role: value.isAi ? 'assistant' : 'user',
                content: { text: value.__plainText },
                metadata: value.metadata,
                model: value.model
            };
        },
        beginGeneration: () => {
            generations++;
        },
        finishGeneration: () => {
            generations--;
        },
        appendMessage: (_slug: string, value: any) => messages.push(value),
        patchMessage: () => {},
        removeCachedMessage: () => {},
        replaceMessage: () => {},
        messagesFor: () => messages,
        findMessage: () => null,
        conversationName: () => 'Title',
        rename: async () => {}
    };
    const context: any = {
        message: 'Keep this draft',
        mode: { isEdit: false, isThread: false },
        model: { current: model },
        modelParameters: { requestParameters: { temperature: 0.4 } },
        systemPrompt: 'System',
        attachments: { list: [] },
        tools: {
            active: requested.map((value) => resolve(value, tools)),
            validatedActive: () => validatedToolSnapshot(requested, tools, model).map((value) => resolve(value, tools))
        }
    };
    const options: any = {
        context,
        status: {
            failed: false,
            hasFileUuid: () => false,
            setFileProgress: () => {},
            setFileUuid: () => {},
            addFileIssue: () => {}
        },
        setResponse: () => {},
        setResponseFailed: (error: string) => errors.push(error),
        waitForResponse: (handler: any) => {
            response = handler({
                setAbortController: () => {},
                triggerBodyChunk: () => {},
                triggerReceived: () => {},
                triggerError: (error: string) => errors.push(error)
            });
        }
    };
    const transport = new ChatTransport(app, store);
    return {
        app,
        tools,
        errors,
        streams,
        writes,
        context,
        options,
        store,
        transport,
        finish: async () => {
            await response;
        },
        generations: () => generations,
        setPacket: (value: any) => {
            packet = value;
        }
    };
}

test('a send uses the same validated tool/settings snapshot for dispatch and persisted metadata', async () => {
    const f = fixture();
    await f.transport.sendMessage(f.options);
    await f.finish();
    assert.deepEqual(f.errors, []);
    assert.equal(f.streams.length, 1);
    assert.deepEqual(f.streams[0].tools, ['capability:web_search:search:{"limit":3}']);
    assert.deepEqual(f.writes.at(-1).metadata.tools, f.streams[0].tools);
    assert.equal(f.generations(), 0);
});

test('regeneration uses the validated historical tool/settings snapshot', async () => {
    const f = fixture();
    await f.transport.regenerateMessage(
        'chat-1',
        {
            message_id: '2',
            model: model.model_id,
            metadata: { tools: ['capability:web_search:search:{"limit":3}'], params: { temperature: 0.2 } }
        } as any,
        null
    );
    assert.equal(f.streams.length, 1);
    assert.deepEqual(f.streams[0].tools, ['capability:web_search:search:{"limit":3}']);
    assert.deepEqual(f.writes.at(-1).metadata.tools, f.streams[0].tools);
    assert.deepEqual(f.writes.at(-1).metadata.params, { temperature: 0.2 });
    assert.equal(f.streams[0].isUpdate, true);
    assert.equal(f.streams[0].messageId, '2');
});

test('regeneration rejects revoked historical tools and never silently retries with a different choice', async () => {
    const f = fixture();
    f.tools.tools = [];
    await assert.rejects(
        f.transport.regenerateMessage(
            'chat-1',
            { message_id: '2', model: model.model_id, metadata: { tools: ['capability:web_search:search'] } } as any,
            null
        ),
        /chat.tools.accessDenied/
    );
    assert.deepEqual(f.streams, []);
    assert.deepEqual(f.writes, []);
    assert.equal(f.generations(), 0);
});

/** The HAWKI base row of the `aiAssistants` hook: no participant claim on a plain regen. */
const hawkiParticipant: any = { id: 'hawki', handle: '@hawki', label: 'HAWKI', description: '' };

test('a regenerate pick whose model cannot use the snapshot tools aborts before dispatching', async () => {
    const snapshot = { tools: ['capability:web_search:search'], params: { temperature: 0.2 } };
    for (const incompatible of [noToolCallingModel, foreignToolModel]) {
        const f = fixture();
        await assert.rejects(
            f.transport.regenerateMessage(
                'chat-1',
                { message_id: '2', model: model.model_id, metadata: snapshot } as any,
                { participant: hawkiParticipant, model: incompatible.model_id }
            ),
            /chat.tools.unavailable/
        );
        assert.deepEqual(f.streams, []);
        assert.deepEqual(f.writes, []);
        assert.equal(f.generations(), 0);
    }
});

test('a tool-free regenerate may switch to a model without tool calling', async () => {
    const f = fixture();
    await f.transport.regenerateMessage(
        'chat-1',
        { message_id: '2', model: model.model_id, metadata: { tools: [] } } as any,
        { participant: hawkiParticipant, model: noToolCallingModel.model_id }
    );
    assert.equal(f.streams.length, 1);
    assert.equal(f.streams[0].model, noToolCallingModel.model_id);
    assert.deepEqual(f.streams[0].tools, []);
    assert.equal(f.writes.at(-1).model, noToolCallingModel.model_id);
});

test('a vanished picked model falls back and reports it, unless a hook claims the run', async () => {
    const notices: string[] = [];
    const plain = fixture();
    await plain.transport.regenerateMessage(
        'chat-1',
        { message_id: '2', model: model.model_id, metadata: { tools: [] } } as any,
        { participant: hawkiParticipant, model: 'gone-model' },
        (notice) => notices.push(notice)
    );
    assert.equal(plain.streams.length, 1);
    assert.equal(plain.streams[0].model, fallbackModel.model_id);
    assert.deepEqual(notices, ['chat.regenerate.modelNotAvailable']);

    // A claimed run's model comes from the participant, so the fallback notice stays silent.
    const claimed = fixture();
    claimed.app.hooks.apply = (name: string, value: any, ctx: any) =>
        name === 'chatSend' && ctx.regenParticipant ?
            { ...value, assistantHandle: 'tutor', model: model.model_id, tools: [] }
        :   value;
    const claimedNotices: string[] = [];
    await claimed.transport.regenerateMessage(
        'chat-1',
        { message_id: '2', model: model.model_id, metadata: { tools: [] } } as any,
        { participant: hawkiParticipant, model: 'gone-model' },
        (notice) => claimedNotices.push(notice)
    );
    assert.equal(claimed.streams.length, 1);
    assert.equal(claimed.streams[0].model, model.model_id);
    assert.deepEqual(claimedNotices, []);
});

test('a regenerate claimed through the chatSend hook runs the hook-resolved run, not the snapshot', async () => {
    const f = fixture();
    f.app.hooks.apply = (name: string, value: any, ctx: any) =>
        name === 'chatSend' && ctx.regenMessage ?
            { ...value, assistantHandle: 'tutor', model: model.model_id, tools: [], params: { temperature: 0.9 } }
        :   value;

    await f.transport.regenerateMessage(
        'chat-1',
        {
            message_id: '2',
            model: model.model_id,
            metadata: { tools: ['capability:web_search:search'], params: { temperature: 0.2 } }
        } as any,
        { participant: hawkiParticipant, model: null }
    );

    assert.equal(f.streams.length, 1);
    assert.equal(f.streams[0].model, model.model_id);
    assert.deepEqual(f.streams[0].tools, []);
    assert.deepEqual(f.streams[0].params, { temperature: 0.9 });
    const write = f.writes.at(-1);
    assert.deepEqual(write.metadata.tools, []);
    assert.deepEqual(write.metadata.params, { temperature: 0.9 });
});

test('a thread run branches from its trunk message: no other turns and not the trunk answer', async () => {
    const f = fixture();
    // A conversation: turn 1, its thread, the trunk question 2.000, its answer 3.000,
    // and two replies in thread 2. The thread opened on the question.
    f.store.messagesFor = () => [
        {message_id: '1.000', message_role: 'user', content: {text: 'earlier question', attachments: []}},
        {message_id: '1.001', message_role: 'assistant', content: {text: 'earlier thread reply', attachments: []}},
        {message_id: '2.000', message_role: 'user', content: {text: 'trunk question', attachments: []}},
        {message_id: '3.000', message_role: 'assistant', content: {text: 'the answer the trunk got', attachments: []}},
        {message_id: '2.001', message_role: 'user', content: {text: 'thread reply one', attachments: []}},
        {message_id: '2.002', message_role: 'assistant', content: {text: 'thread answer one', attachments: []}},
        {message_id: '2.003', message_role: 'assistant', content: {text: 'thread answer two', attachments: []}}
    ] as any;

    await f.transport.regenerateMessage('chat-1', {message_id: '2.003', model: model.model_id, metadata: {tools: []}} as any, null);

    const texts = f.streams[0].messages.slice(1).map((entry: any) => entry.content?.text);
    // The run sees its trunk and the thread's earlier messages only.
    assert.deepEqual(texts.filter(Boolean), ['trunk question', 'thread reply one', 'thread answer one']);
    // No inheritance of the surrounding conversation — not the earlier turn, not the
    // other thread, and not the answer the trunk question got in the main log (that
    // answer lives under the next whole id and never belongs to the thread).
    assert.equal(texts.some((text: any) => text === 'earlier question' || text === 'earlier thread reply' || text === 'the answer the trunk got'), false);
});

test('a thread opened on a response keeps the response but not the question that prompted it', async () => {
    const f = fixture();
    f.store.messagesFor = () => [
        {message_id: '2.000', message_role: 'user', content: {text: 'the question', attachments: []}},
        {message_id: '3.000', message_role: 'assistant', content: {text: 'the response', attachments: []}},
        {message_id: '3.001', message_role: 'user', content: {text: 'follow-up in the thread', attachments: []}},
        {message_id: '3.002', message_role: 'assistant', content: {text: 'thread answer', attachments: []}}
    ] as any;

    await f.transport.regenerateMessage('chat-1', {message_id: '3.002', model: model.model_id, metadata: {tools: []}} as any, null);

    const messages = f.streams[0].messages;
    // The thread's history opens with the assistant response it was started from.
    assert.equal(messages.length, 3);
    assert.equal(messages[1].role, 'assistant');
    assert.equal(messages[1].content.text, 'the response');
    assert.equal(messages[2].content.text, 'follow-up in the thread');
    assert.equal(messages.some((entry: any) => entry.content?.text === 'the question'), false);
});

test('a short thread keeps its anchoring trunk message under the history cap', async () => {
    const f = fixture();
    f.store.messagesFor = () => [
        {message_id: '2.000', message_role: 'user', content: {text: 'trunk question', attachments: []}},
        {message_id: '2.001', message_role: 'user', content: {text: 'thread reply one', attachments: []}},
        {message_id: '2.002', message_role: 'assistant', content: {text: 'thread answer one', attachments: []}},
        {message_id: '2.003', message_role: 'assistant', content: {text: 'thread answer two', attachments: []}}
    ] as any;

    await f.transport.regenerateMessage('chat-1', {message_id: '2.003', model: model.model_id, metadata: {tools: []}} as any, null);

    const messages = f.streams[0].messages;
    // System prompt + trunk + the thread's messages before the regenerated one.
    assert.equal(messages.length, 4);
    assert.equal(messages[1].content.text, 'trunk question');
    assert.equal(messages[2].content.text, 'thread reply one');
    assert.equal(messages[3].content.text, 'thread answer one');
});

test('a thread-mode send persists the reply into the thread and streams trunk plus replies', async () => {
    const f = fixture();
    f.context.mode = {
        isEdit: false,
        isThread: true,
        isRegen: false,
        getState: () => ({threadId: '2'})
    };
    f.store.messagesFor = () => [
        {message_id: '2.000', message_role: 'user', content: {text: 'trunk question', attachments: []}},
        {message_id: '2.001', message_role: 'assistant', content: {text: 'thread answer one', attachments: []}}
    ] as any;
    // The just-persisted thread reply joins the store before the run streams.
    const persist = f.store.persistMessage;
    f.store.persistMessage = async (slug: string, value: any) => {
        const saved = await persist(slug, value);
        if (!value.isAi) f.store.messagesFor = () => [
            {message_id: '2.000', message_role: 'user', content: {text: 'trunk question', attachments: []}},
            {message_id: '2.001', message_role: 'assistant', content: {text: 'thread answer one', attachments: []}},
            {message_id: '2.002', message_role: 'user', content: {text: value.__plainText, attachments: []}}
        ] as any;
        return saved;
    };

    await f.transport.sendMessage(f.options);
    await f.finish();

    assert.deepEqual(f.errors, []);
    // The user reply is persisted into the thread…
    assert.equal(f.writes[0].isAi, false);
    assert.equal(f.writes[0].threadId, 2);
    // …and the run sees trunk + earlier reply + the new reply, with the thread index set.
    assert.equal(f.streams.length, 1);
    assert.equal(f.streams[0].threadIndex, 2);
    const texts = f.streams[0].messages.slice(1).map((entry: any) => entry.content?.text);
    assert.deepEqual(texts.filter(Boolean), ['trunk question', 'thread answer one', 'Keep this draft']);
});

test('a regen-mode send re-runs the reply in place without persisting a user message', async () => {
    const f = fixture();
    f.context.mode = {
        isEdit: false,
        isThread: false,
        isRegen: true,
        getState: () => ({messageId: '2.003', originalMessage: 'Original answer', pickedParticipant: null})
    };

    await f.transport.sendMessage(f.options);
    await f.finish();

    assert.deepEqual(f.errors, []);
    assert.equal(f.streams.length, 1);
    // The assistant reply is replaced in place: update flag, thread and id derived
    // from the regenerated message's W.DDD id — and no user message was shown or
    // persisted (only the AI write remains).
    assert.equal(f.streams[0].isUpdate, true);
    assert.equal(f.streams[0].messageId, '2.003');
    assert.equal(f.streams[0].threadIndex, 2);
    assert.equal(f.writes.length, 1);
    assert.equal(f.writes[0].isAi, true);
    assert.equal(f.writes[0].message_id, '2.003');
    assert.deepEqual(f.writes[0].metadata.tools, ['capability:web_search:search:{"limit":3}']);
});

test('a regen-mode send with a picked participant hands it to the chatSend hook', async () => {
    const f = fixture();
    f.context.mode = {
        isEdit: false,
        isThread: false,
        isRegen: true,
        getState: () => ({messageId: '2', originalMessage: 'Original answer', pickedParticipant: {id: 'assistant:tutor', handle: '@tutor', label: 'Tutor', description: ''}})
    };
    f.app.hooks.apply = (name: string, value: any, ctx: any) => name === 'chatSend' && ctx.regenParticipant
        ? { ...value, assistantHandle: 'tutor' }
        : value;

    await f.transport.sendMessage(f.options);
    await f.finish();

    assert.equal(f.streams.length, 1);
    assert.equal(f.streams[0].assistantHandle, 'tutor');
});

test('a regen-mode send whose composer tools the model cannot run aborts before dispatch', async () => {
    const f = fixture();
    f.context.mode = {
        isEdit: false,
        isThread: false,
        isRegen: true,
        getState: () => ({messageId: '2', originalMessage: 'Original answer', pickedParticipant: null})
    };
    f.context.model = {current: noToolCallingModel};

    await f.transport.sendMessage(f.options);

    assert.deepEqual(f.errors, ['chat.tools.unavailable']);
    assert.deepEqual(f.streams, []);
    assert.deepEqual(f.writes, []);
});

test(`a thread run sends only the last ${MESSAGE_HISTORY_CAP} thread messages to the model`, async () => {
    const f = fixture();
    const thread = [
        { message_id: '2.000', message_role: 'user', content: { text: 'trunk question', attachments: [] } },
        ...Array.from({ length: 23 }, (_, index) => ({
            message_id: `2.${String(index + 1).padStart(3, '0')}`,
            message_role: index % 2 === 0 ? 'user' : 'assistant',
            content: { text: `reply ${index + 1}`, attachments: [] }
        }))
    ];
    f.store.messagesFor = () => thread as any;
    await f.transport.regenerateMessage(
        'chat-1',
        { message_id: '2.023', model: model.model_id, metadata: { tools: [] } } as any,
        null
    );
    const messages = f.streams[0].messages;
    // System prompt + the capped history: trunk + replies 1..22 are 23 messages, the last 20 of which are replies 3..22.
    assert.equal(messages.length, 21);
    assert.equal(messages[0].role, 'system');
    assert.equal(messages[1].content.text, 'reply 3');
    assert.equal(messages.at(-1).content.text, 'reply 22');
    assert.equal(
        messages.some((entry: any) => entry.content?.text === 'trunk question'),
        false
    );
});
