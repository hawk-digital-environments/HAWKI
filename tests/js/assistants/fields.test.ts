import assert from 'node:assert/strict';
import {test} from 'node:test';
import {fieldLabel, humanizeKey} from '../../../resources/js/plugins/assistants/admin/api/fields.js';

/** Translates by returning the key; assertions compare keys for known fields. */
const keyAsLabel = (key: string) => key;

test('fields: known payload keys map to their i18n label keys', () => {
    assert.equal(fieldLabel('system_prompt', keyAsLabel), 'assistants.admin.detail.fields.system_prompt');
    assert.equal(fieldLabel('temp', keyAsLabel), 'assistants.admin.detail.fields.temp');
    assert.equal(fieldLabel('category_id', keyAsLabel), 'assistants.admin.detail.fields.category');
});

test('fields: the edit-journal and payload spellings share one label', () => {
    const journal = fieldLabel('assistant_tags', keyAsLabel);
    const payload = fieldLabel('tag_ids', keyAsLabel);
    assert.equal(journal, 'assistants.admin.detail.fields.tags');
    assert.equal(journal, payload);
});

test('fields: unknown keys fall back to the humanized spelling', () => {
    const seen: string[] = [];
    const translate = (key: string) => {
        seen.push(key);
        return key;
    };

    assert.equal(fieldLabel('some_new_field', translate), 'some new field');
    assert.equal(seen.length, 0, 'the fallback must not ask the translator');
});

test('fields: humanizeKey only touches underscores', () => {
    assert.equal(humanizeKey('detail_description'), 'detail description');
    assert.equal(humanizeKey('plain'), 'plain');
});
