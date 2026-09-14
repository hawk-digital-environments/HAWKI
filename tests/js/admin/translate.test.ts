import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { translateLocales, translationMessages } from '../../../resources/js/plugins/admin/forms/translateLocales.js';

const en = { lang: 'en_US', name: 'English' };
const de = { lang: 'de_DE', name: 'Deutsch' };
const fr = { lang: 'fr_FR', name: 'Français' };

test('translates into every locale except the source and keeps the source untouched', async () => {
    const requested: string[] = [];
    const result = await translateLocales({
        text: '# Hello',
        source: en,
        targets: [en, de, fr],
        complete: async (messages) => {
            const prompt = messages[0].content.text ?? '';
            requested.push(prompt);
            return `  translated for ${prompt.includes('(de_DE)') ? 'de' : 'fr'} `;
        }
    });
    assert.deepEqual(result, { de_DE: 'translated for de', fr_FR: 'translated for fr' });
    assert.equal(requested.length, 2);
    assert.ok(requested.every((prompt) => prompt.includes('from English (en_US)')));
});

test('drops empty answers and rejects when a request fails', async () => {
    const empty = await translateLocales({ text: 'x', source: en, targets: [en, de], complete: async () => '   ' });
    assert.deepEqual(empty, {});
    await assert.rejects(
        translateLocales({ text: 'x', source: en, targets: [en, de], complete: async () => { throw new Error('boom'); } }),
        /boom/
    );
});

test('the prompt carries the Markdown text as the user message', () => {
    const messages = translationMessages('**bold**', en, de);
    assert.equal(messages[0].role, 'system');
    assert.ok(messages[0].content.text?.includes('into Deutsch (de_DE)'));
    assert.deepEqual(messages[1], { role: 'user', content: { text: '**bold**' } });
});
