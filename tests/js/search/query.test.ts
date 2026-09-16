import {strict as assert} from 'node:assert';
import test, {describe} from 'node:test';
import {normalizeSearchQuery, queryLength} from '$lib/kernel/search/query.js';

describe('normalizeSearchQuery', () => {
    test('collapses whitespace, trims and lower-cases', () => {
        assert.equal(normalizeSearchQuery('  Chat   Design\tNotes \n'), 'chat design notes');
    });

    test('folds full-width and decomposed input onto its plain form', () => {
        assert.equal(normalizeSearchQuery('ＣＨＡＴ'), 'chat');
        // "e" + combining acute must match the precomposed "é".
        assert.equal(normalizeSearchQuery('Café'), normalizeSearchQuery('Café'));
    });

    test('treats a blank input as an empty query', () => {
        assert.equal(normalizeSearchQuery('   '), '');
        assert.equal(normalizeSearchQuery(''), '');
    });
});

describe('queryLength', () => {
    test('counts codepoints, not UTF-16 units', () => {
        // A single astral character must not pass the two-character minimum.
        assert.equal(queryLength('🙂'), 1);
        assert.equal(queryLength('ab'), 2);
        assert.equal(queryLength(''), 0);
    });
});
