import assert from 'node:assert/strict';
import {test} from 'node:test';
import {createChatConfig, REQUIRED_FIELD_LABELS} from '../../../resources/js/plugins/assistants/components/testChat/stream/chatConfig.svelte.js';
import {buildStreamRequestBody} from '../../../resources/js/plugins/assistants/components/testChat/stream/chatStore.svelte.js';

/** Minimal draft factory: only the fields the config/payload consume. */
function draft(overrides = {}) {
    return {
        name: 'Test',
        handle: 'test-assistant',
        model: 'gpt-test',
        systemPrompt: '',
        aiTools: [],
        temp: 0.7,
        topP: 0.95,
        ...overrides,
    };
}

test('missingRequired: system prompt and tools both empty lists what is missing', () => {
    // Unsaved draft: system prompt AND handle missing.
    const config = createChatConfig(() => draft({handle: null}));
    assert.deepEqual(config.missingRequired, ['systemPrompt', 'handle']);
});

test('missingRequired: a saved assistant only lacks the system prompt', () => {
    const config = createChatConfig(() => draft());
    assert.deepEqual(config.missingRequired, ['systemPrompt']);
});

test('missingRequired: a system prompt or any tool satisfies the trigger', () => {
    assert.deepEqual(
        createChatConfig(() => draft({systemPrompt: 'You are helpful.'})).missingRequired,
        []
    );
    assert.deepEqual(
        createChatConfig(() => draft({aiTools: [{name: 'hawki-rag-full-emb-hawk'}]})).missingRequired,
        []
    );
});

test('missingRequired: never shown for the guide variant', () => {
    const config = createChatConfig(() => draft({handle: null}), 'guide');
    assert.deepEqual(config.missingRequired, []);
});

test('openField rides along for the chips, absent by default', () => {
    const openField = () => {};
    assert.equal(createChatConfig(() => draft(), 'test', {openField}).openField, openField);
    assert.equal(createChatConfig(() => draft()).openField, undefined);
});

test('required chips reuse the builder field labels', () => {
    assert.equal(REQUIRED_FIELD_LABELS.systemPrompt, 'assistants.builder.behaviour.input_system_prompt');
    assert.equal(REQUIRED_FIELD_LABELS.handle, 'assistants.builder.general.input_handle');
});

test('payload: saved assistants stream through the assistant route without raw tools', () => {
    const body = buildStreamRequestBody(draft(), [], 'test');
    const payload = body.payload;

    assert.deepEqual(payload.hawkiExtensions, {assistant_handle: 'test-assistant'});
    assert.equal(payload.tools, undefined, 'tools come from server-side composition');
    assert.equal(payload.model, 'gpt-test');
});

test('payload: never-saved drafts keep the raw-fields contract', () => {
    const body = buildStreamRequestBody(
        draft({handle: null, aiTools: [{name: 'hawki-rag-full-emb-hawk'}]}),
        [],
        'test',
    );
    const payload = body.payload;

    assert.equal(payload.hawkiExtensions, undefined);
    assert.deepEqual(payload.tools, ['hawki-rag-full-emb-hawk']);
});

test('payload: an empty handle string is treated as unsaved', () => {
    const body = buildStreamRequestBody(draft({handle: ''}), [], 'test');
    assert.equal(body.payload.hawkiExtensions, undefined);
    assert.deepEqual(body.payload.tools, []);
});
