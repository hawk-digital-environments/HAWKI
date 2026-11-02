import assert from 'node:assert/strict';
import {test} from 'node:test';
import {assistantChatSend} from '../../../resources/js/plugins/assistants/hooks/assistantChatHooks.js';
import {assistantHandlesStore} from '../../../resources/js/plugins/assistants/stores/AssistantHandlesStore.svelte.js';
import type {Assistant} from '../../../resources/js/plugins/assistants/types/assistant';
import type {ChatSendContext, ChatSendDescriptor} from '../../../resources/js/plugins/core/modules/chat/hooks/chatSendHooks.js';
import type {AiAssistant} from '../../../resources/js/plugins/core/stores/AiHandleStore.svelte.js';

/** Minimal assistant factory: only the fields `assistantChatSend` consumes. */
function assistant(overrides: Record<string, unknown> = {}): Assistant {
    return {
        id: 'uuid-1',
        handle: 'tutor',
        name: 'Tutor',
        description: '',
        model: 'tutor-model',
        allowModelSelect: false,
        aiTools: [],
        temp: 0.3,
        topP: 0.8,
        maxTokens: 1024,
        avatar: null,
        ...overrides
    } as unknown as Assistant;
}

/** The `aiAssistants` hook row shape the regenerate menu picks, as the plugin builds it. */
function participantOf(source: Assistant): AiAssistant {
    return {
        id: `assistant:${source.id}`,
        handle: `@${source.handle}`,
        label: source.name,
        description: source.description,
        chatBinding: source.handle ?? undefined
    };
}

/** The HAWKI base row: the default participant, which carries no chat binding. */
const hawkiParticipant: AiAssistant = {id: 'hawki', handle: '@hawki', label: 'HAWKI', description: ''};

const baseSend: ChatSendDescriptor = {
    assistantHandle: null,
    assistant: null,
    author: null,
    model: 'composer-model',
    tools: ['capability:web_search:search'],
    params: {temperature: 0.4}
};

function ctx(overrides: Partial<ChatSendContext> = {}): ChatSendContext {
    return {composer: null, conversation: null, regenMessage: null, regenParticipant: null, ...overrides};
}

test('a regenerate pick is pinned to the participant its row claims', () => {
    const tutor = assistant();
    assistantHandlesStore.assistants = [tutor];

    const send = assistantChatSend(baseSend, ctx({regenParticipant: participantOf(tutor)}));

    assert.equal(send.assistantHandle, 'tutor');
    assert.equal(send.model, 'tutor-model');
    assert.deepEqual(send.tools, []);
    assert.deepEqual(send.params, {temp: 0.3, top_p: 0.8, max_tokens: 1024});
    assert.equal(send.assistant?.name, 'Tutor');
    assert.equal(send.assistant?.handle, 'tutor');
    assert.equal(send.author?.username, 'tutor');
    assert.equal(send.author?.name, 'Tutor');
});

test('an allowModelSelect assistant keeps the model of the run it was picked for', () => {
    const tutor = assistant({allowModelSelect: true});
    assistantHandlesStore.assistants = [tutor];

    const send = assistantChatSend(baseSend, ctx({regenParticipant: participantOf(tutor)}));

    assert.equal(send.assistantHandle, 'tutor');
    assert.equal(send.model, 'composer-model');
});

test("HAWKI's row carries no binding, so the rerun stays plain and user-configured", () => {
    assistantHandlesStore.assistants = [assistant()];

    const send = assistantChatSend(baseSend, ctx({regenParticipant: hawkiParticipant}));

    assert.deepEqual(send, baseSend);
});

test("a row whose assistant no longer exists is unclaimed, so the rerun stays plain", () => {
    assistantHandlesStore.assistants = [];

    const send = assistantChatSend(baseSend, ctx({regenParticipant: {
        id: 'assistant:gone', handle: '@gone', label: 'Gone', description: '', chatBinding: 'gone'
    }}));

    assert.deepEqual(send, baseSend);
});

test('a composer send is still pinned through its live @handles', () => {
    const tutor = assistant();
    assistantHandlesStore.assistants = [tutor];

    const send = assistantChatSend(baseSend, ctx({composer: {handlesInMessage: ['@tutor']} as never}));

    assert.equal(send.assistantHandle, 'tutor');
    assert.equal(send.model, 'tutor-model');
});
