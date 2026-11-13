import assert from 'node:assert/strict';
import {test} from 'node:test';
import {assistantRegenerateGroups} from '../../../resources/js/plugins/assistants/hooks/assistantChatHooks.js';
import {assistantHandlesStore} from '../../../resources/js/plugins/assistants/stores/AssistantHandlesStore.svelte.js';
import type {ChatRegenerateGroup} from '../../../resources/js/plugins/core/modules/chat/hooks/chatRegenerateHooks.js';

const translate = (key: string) => key;
const providerGroup: ChatRegenerateGroup = {
    id: 'model:Test',
    label: 'Test',
    entries: [{id: 'test-model', participant: {id: 'hawki', handle: '@hawki', label: 'HAWKI', description: ''}, model: 'test-model'}]
};

test('the Assistants group sits ahead of the model providers and carries one entry per assistant', () => {
    assistantHandlesStore.assistants = [
        {id: 'uuid-1', handle: 'tutor', name: 'Tutor', description: '', avatar: null} as never,
        {id: 'uuid-2', handle: 'coach', name: 'Coach', description: '', avatar: null} as never
    ];

    const groups = assistantRegenerateGroups([providerGroup], {translate});

    assert.equal(groups.length, 2);
    assert.equal(groups[0].id, 'assistants');
    assert.equal(groups[0].label, 'chat.composer.assistantMenu.assistantsLabel');
    assert.deepEqual(groups[0].entries.map(entry => [entry.id, entry.participant.chatBinding]), [
        ['assistant:uuid-1', 'tutor'],
        ['assistant:uuid-2', 'coach']
    ]);
    assert.equal(groups[1], providerGroup);
});

test('an empty store contributes an empty group, which the menu hides', () => {
    assistantHandlesStore.assistants = [];

    const groups = assistantRegenerateGroups([providerGroup], {translate});

    assert.deepEqual(groups[0].entries, []);
    assert.equal(groups[1], providerGroup);
});

test('a deactivated assistant is not offered, an offline one is offered disabled', () => {
    const models: any = {
        authorizationState: 'ready',
        getOneById: (id: string) => ({'live-model': {status: 'online'}, 'down-model': {status: 'offline'}} as any)[id] ?? null
    };
    assistantHandlesStore.ready({stores: {get: () => models}} as never);
    assistantHandlesStore.assistants = [
        {id: 'uuid-1', handle: 'tutor', name: 'Tutor', description: '', avatar: null, model: 'live-model'} as never,
        {id: 'uuid-2', handle: 'coach', name: 'Coach', description: '', avatar: null, model: 'down-model'} as never,
        {id: 'uuid-3', handle: 'retired', name: 'Retired', description: '', avatar: null, model: 'retired-model'} as never
    ];

    try {
        const groups = assistantRegenerateGroups([providerGroup], {translate});
        assert.deepEqual(groups[0].entries.map(entry => [entry.participant.chatBinding, entry.disabled]), [
            ['tutor', false],
            ['coach', true]
        ]);
    } finally {
        assistantHandlesStore.ready({stores: {get: () => null}} as never);
    }
});
