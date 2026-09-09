import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {readFileSync} from 'node:fs';
import {compile} from 'svelte/compiler';

test('the compiled recovery input accepts the format written to backup files', () => {
    const source = readFileSync(new URL('../../../resources/js/plugins/core/pages/auth/Handshake.svelte', import.meta.url), 'utf8');
    const compiled = compile(source, {filename: 'Handshake.svelte', generate: 'client'}).js.code;
    // Test the emitted attribute: unescaped {4} in Svelte markup becomes a literal 4.
    const attribute = compiled.match(/\bpattern:\s*(['"])(.*?)\1/);
    assert.ok(attribute, 'Recovery input must expose a format pattern');
    const pattern = new RegExp(`^(?:${attribute[2]})$`);
    assert.equal(pattern.test('abcd-1234-5678-90ef'), true);
    assert.equal(pattern.test('ABCD-1234-5678-90EF'), true);
    assert.equal(pattern.test('abcd-1234-5678'), false);
    assert.equal(pattern.test('abcd-1234-5678-90eg'), false);
});
