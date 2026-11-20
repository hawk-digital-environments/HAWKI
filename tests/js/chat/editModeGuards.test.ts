import assert from 'node:assert/strict';
import {test} from 'node:test';
import {ChatEditMode} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/modes/ChatEditMode.js';
import type {DisabledChatFeature} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/GuardSlice.svelte.js';

const editMode = new ChatEditMode();

test('edit mode locks the run configuration and the assistant addressing UI', () => {
    for (const feature of ['models', 'settings', 'tools', 'assistants'] as DisabledChatFeature[]) {
        assert.equal(editMode.disablesUiFeature(feature), true, feature);
    }
});

test('edit mode leaves input, attachments and suggestions interactive', () => {
    for (const feature of ['input', 'attachments', 'suggestions'] as DisabledChatFeature[]) {
        assert.equal(editMode.disablesUiFeature(feature), false, feature);
    }
});
