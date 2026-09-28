import assert from 'node:assert/strict';
import {test} from 'node:test';
import {firstFreeHandle, handleCandidates} from '../../../resources/js/plugins/assistants/modules/builder/contexts/builderUtils.js';

test('handle candidates are the slug plus its -2…-N variants', () => {
    assert.deepEqual(
        handleCandidates('tutor', 3),
        ['tutor', 'tutor-2', 'tutor-3'],
    );
});

test('long slugs truncate so the suffix still fits the 255-char handle cap', () => {
    const base = 'a'.repeat(255);
    const candidates = handleCandidates(base, 20);

    for (const candidate of candidates) {
        assert.ok(candidate.length <= 255, `${candidate.length} must stay within the handle cap`);
    }
    assert.equal(candidates[1], 'a'.repeat(253) + '-2');
});

test('firstFreeHandle picks the first candidate the server does not know', () => {
    const candidates = ['tutor', 'tutor-2', 'tutor-3'];

    assert.equal(firstFreeHandle(candidates, ['tutor']), 'tutor-2');
    assert.equal(firstFreeHandle(candidates, ['tutor', 'tutor-2']), 'tutor-3');
    assert.equal(firstFreeHandle(candidates, []), 'tutor');
});

test('firstFreeHandle returns null when every candidate is taken', () => {
    assert.equal(firstFreeHandle(['tutor', 'tutor-2'], ['tutor', 'tutor-2']), null);
});
