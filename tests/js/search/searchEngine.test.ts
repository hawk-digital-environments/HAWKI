import {describe, it} from 'node:test';
import assert from 'node:assert/strict';
import {SearchEngine, type SearchDocument, type SearchScores} from '$lib/kernel/search/searchEngine.js';

function document(id: string, fields: Partial<Omit<SearchDocument, 'id'>> = {}): SearchDocument {
    return {id, title: '', keywords: '', content: '', ...fields};
}

function keys(scores: SearchScores): string[] {
    return scores.map(([key]) => key);
}

describe('SearchEngine', () => {
    it('answers a blank query and an empty index with nothing', () => {
        const engine = new SearchEngine();
        assert.deepEqual(engine.search('alpha'), []);

        engine.upsert(document('a', {title: 'Alpha'}));
        assert.deepEqual(engine.search('   '), []);
    });

    it('matches prefixes across every indexed field', () => {
        const engine = new SearchEngine();
        engine.upsert(document('title', {title: 'Deployment checklist'}));
        engine.upsert(document('keywords', {title: 'Release notes', keywords: 'deployment rollout'}));
        engine.upsert(document('content', {title: 'Meeting', content: 'we discussed the deployment'}));

        assert.deepEqual(keys(engine.search('deploy')).sort(), ['content', 'keywords', 'title']);
    });

    it('ranks a title hit above a body hit', () => {
        const engine = new SearchEngine();
        engine.upsert(document('body', {title: 'Weekly notes', content: 'a long note about the deployment of the release'}));
        engine.upsert(document('title', {title: 'Deployment'}));

        assert.equal(keys(engine.search('deployment'))[0], 'title');
    });

    it('requires every term to match', () => {
        const engine = new SearchEngine();
        engine.upsert(document('a', {title: 'Deployment checklist'}));

        assert.deepEqual(keys(engine.search('deployment checklist')), ['a']);
        assert.deepEqual(engine.search('deployment zebra'), []);
    });

    it('forgives a single typo', () => {
        const engine = new SearchEngine();
        engine.upsert(document('a', {title: 'Settings'}));

        assert.deepEqual(keys(engine.search('setings')), ['a']);
    });

    it('replaces the text of a document it already holds', () => {
        const engine = new SearchEngine();
        engine.upsert(document('a', {title: 'Alpha'}));
        engine.upsert(document('a', {title: 'Beta'}));

        assert.equal(engine.size, 1);
        assert.deepEqual(engine.search('alpha'), []);
        assert.deepEqual(keys(engine.search('beta')), ['a']);
    });

    it('removes a document, and shrugs at an unknown key', () => {
        const engine = new SearchEngine();
        engine.upsert(document('a', {title: 'Alpha'}));

        engine.remove('unknown');
        assert.equal(engine.size, 1);

        engine.remove('a');
        assert.equal(engine.size, 0);
        assert.deepEqual(engine.search('alpha'), []);
    });

    it('swaps the whole corpus on replace', () => {
        const engine = new SearchEngine();
        engine.upsert(document('a', {title: 'Alpha'}));
        engine.replace([document('b', {title: 'Beta'}), document('c', {title: 'Gamma'})]);

        assert.equal(engine.size, 2);
        assert.deepEqual(engine.search('alpha'), []);
        assert.deepEqual(keys(engine.search('beta')), ['b']);

        engine.clear();
        assert.equal(engine.size, 0);
    });
});
