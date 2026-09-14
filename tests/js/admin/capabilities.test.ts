import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import {
    hasModelCapability,
    isModelVisible,
    modelCapabilities,
    toggleModelCapability,
    toggleModelVisible
} from '../../../resources/js/plugins/admin/capabilities.js';

const row = {
    id: '1',
    input: ['text', 'image'],
    output: ['text'],
    native_capabilities: ['web_search'],
    settings: { file_upload: true, tool_calling: false, max_tool_calling_rounds: 3 }
};

test('capabilities are read from modalities, native capabilities and settings', () => {
    assert.deepEqual(Object.fromEntries(modelCapabilities.map((id) => [id, hasModelCapability(row, id)])), {
        file_upload: true,
        vision: true,
        tool_calling: false,
        web_search: true,
        code_execution: false,
        image_generation: false
    });
    assert.equal(hasModelCapability({ id: '2' }, 'vision'), false);
    assert.equal(hasModelCapability({ id: '2', settings: [] }, 'file_upload'), false);
});

test('toggling a capability only changes its own entry of the field', () => {
    assert.deepEqual(toggleModelCapability(row, 'tool_calling', true), {
        settings: { file_upload: true, tool_calling: true, max_tool_calling_rounds: 3 }
    });
    assert.deepEqual(toggleModelCapability(row, 'vision', false), { input: ['text'] });
    assert.deepEqual(toggleModelCapability(row, 'image_generation', true), { output: ['text', 'image'] });
    assert.deepEqual(toggleModelCapability(row, 'code_execution', true), {
        native_capabilities: ['web_search', 'code_execution']
    });
    assert.deepEqual(toggleModelCapability(row, 'web_search', false), { native_capabilities: [] });
});

test('toggling works on rows without the field and never duplicates entries', () => {
    assert.deepEqual(toggleModelCapability({ id: '2' }, 'file_upload', true), { settings: { file_upload: true } });
    assert.deepEqual(toggleModelCapability({ id: '2', settings: [] }, 'file_upload', false), {
        settings: { file_upload: false }
    });
    assert.deepEqual(toggleModelCapability(row, 'vision', true), { input: ['text', 'image'] });
    assert.deepEqual(toggleModelCapability({ id: '2' }, 'web_search', true), { native_capabilities: ['web_search'] });
});

test('visibility follows the main usage rule and keeps other usage rules', () => {
    assert.equal(isModelVisible({ id: '1', usage_rules: ['external', 'main'] }), true);
    assert.equal(isModelVisible({ id: '1', usage_rules: ['external'] }), false);
    assert.equal(isModelVisible({ id: '1' }), false);
    assert.deepEqual(toggleModelVisible({ id: '1', usage_rules: ['external', 'main'] }, false), {
        usage_rules: ['external']
    });
    assert.deepEqual(toggleModelVisible({ id: '1', usage_rules: ['external'] }, true), {
        usage_rules: ['external', 'main']
    });
    assert.deepEqual(toggleModelVisible({ id: '1' }, true), { usage_rules: ['main'] });
});
