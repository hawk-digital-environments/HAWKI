import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { ChatEditMode } from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/modes/ChatEditMode.js';

const editMode = new ChatEditMode();

test('edit mode locks the run configuration', () => {
    for (const feature of ['models', 'settings', 'tools'] as const) {
        assert.equal(editMode.disablesUiFeature(feature), true, feature);
    }
});

test('edit mode leaves input, attachments and suggestions interactive', () => {
    for (const feature of ['input', 'attachments', 'suggestions'] as const) {
        assert.equal(editMode.disablesUiFeature(feature), false, feature);
    }
});
