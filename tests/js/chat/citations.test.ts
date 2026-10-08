import assert from 'node:assert/strict';
import {test} from 'node:test';
import {normalizeDocumentName, rewriteDocumentCitationMarkers} from '../../../resources/js/plugins/core/modules/chat/components/message/rewriteDocumentCitationMarkers.js';
import {dedupeCitations} from '../../../resources/js/plugins/core/modules/chat/components/message/dedupeCitations.js';
import {CITATION_ANCHOR_PREFIX} from '../../../resources/js/plugins/core/modules/chat/components/message/injectCitationsIntoMarkdown.js';

/** Minimal enriched-citation factory; only the fields the rewrite consumes matter. */
function citation(overrides = {}) {
    return {
        url: 'https://example.test/doc',
        title: null,
        ranges: [],
        identifier: 'id-' + Math.random().toString(36).slice(2, 8),
        document: false,
        ...overrides
    };
}

/** Identifier factory for dedupe tests: deterministic, one fresh id per entry. */
function sequencedIdentifier() {
    let n = 0;
    return () => `mount-${++n}`;
}

test('normalization is trim/whitespace/case only — no structural guessing', () => {
    assert.equal(normalizeDocumentName('  Pisa-Studie_  DIE ZEIT.PDF '), 'pisa-studie_ die zeit.pdf');
    // Exact names up to trivial variance; no suffix or extension tricks.
    assert.equal(normalizeDocumentName('Report-3-1.pdf'), 'report-3-1.pdf');
    assert.equal(normalizeDocumentName('Notes.txt'), 'notes.txt');
});

test('document markers resolve to numbered chips for matching document citations', () => {
    const docs = [
        citation({document: true, title: 'Pisa-Studie_ Die Impfung gegen Dummheit _ DIE ZEIT.pdf', identifier: 'doc-1'}),
        citation({document: true, title: 'notes.txt', identifier: 'doc-2'})
    ];

    const result = rewriteDocumentCitationMarkers(
        'Leistungen sinken [[Pisa-Studie_ Die Impfung gegen Dummheit _ DIE ZEIT.pdf]] weltweit.',
        docs
    );

    assert.equal(result, 'Leistungen sinken [1](#citation-doc-1) weltweit.');
});

test('markers match case/whitespace variance but not structurally different names', () => {
    const docs = [citation({document: true, title: 'Report.pdf', identifier: 'doc-1'})];

    // Trivial variance resolves…
    assert.equal(
        rewriteDocumentCitationMarkers('Claim [[ report.PDF ]] here.', docs),
        'Claim [1](#citation-doc-1) here.'
    );
    // …a suffixed name is a different document name: honest degradation, never a guessed link.
    assert.equal(
        rewriteDocumentCitationMarkers('Claim [[Report-3-1.pdf]] here.', docs),
        'Claim [Report-3-1.pdf] here.'
    );
});

test('side-by-side markers for one claim become adjacent chips', () => {
    const docs = [
        citation({title: 'https://web.example', identifier: 'web-1'}), // web source first: numbering follows tile order
        citation({document: true, title: 'a.pdf', identifier: 'doc-1'}),
        citation({document: true, title: 'b.pdf', identifier: 'doc-2'})
    ];

    assert.equal(
        rewriteDocumentCitationMarkers('Claim [[a.pdf]][[b.pdf]].', docs),
        'Claim [2](#citation-doc-1)[3](#citation-doc-2).'
    );
});

test('messages without document citations keep [[…]] verbatim — no rewrite at all', () => {
    // The rewrite is exclusive to knowledge answers. Web-only messages
    // (provider citations) pass through untouched.
    const web = [citation({title: 'report.pdf', identifier: 'web-1', document: false})];

    assert.equal(
        rewriteDocumentCitationMarkers('Claim [[report.pdf]].', web),
        'Claim [[report.pdf]].'
    );

    // No citations at all (plain chat, other models): byte-identical, so
    // code like bash conditionals or TOML headers is never mangled.
    assert.equal(
        rewriteDocumentCitationMarkers('Lost [[report.pdf]] citations.', []),
        'Lost [[report.pdf]] citations.'
    );
});

test('plain-chat code with [[…]] semantics is never touched: bash and TOML', () => {
    const bash = 'Run `[[ -f "$x" ]] && echo ok` or:\n\n```bash\nif [[ -f "$HOME/.profile" ]]; then\n  source "$HOME/.profile"\nfi\n```';
    const toml = '```toml\n[[package]]\nname = "hawki"\n```';

    assert.equal(rewriteDocumentCitationMarkers(bash, []), bash);
    assert.equal(rewriteDocumentCitationMarkers(toml, []), toml);
});

test('code segments stay verbatim while prose markers resolve in the same message', () => {
    const docs = [citation({document: true, title: 'setup.md', identifier: 'doc-1'})];
    const markdown = [
        'Follow [[setup.md]] first, then:',
        '',
        '```bash',
        'if [[ -f ".env" ]]; then source ".env"; fi',
        '```',
        '',
        'Inline code `[[setup.md]]` stays, prose [[setup.md]] resolves.',
        '',
        '~~~toml',
        '[[bin]]',
        'name = "tool"',
        '~~~'
    ].join('\n');

    assert.equal(
        rewriteDocumentCitationMarkers(markdown, docs),
        [
            'Follow [1](#citation-doc-1) first, then:',
            '',
            '```bash',
            'if [[ -f ".env" ]]; then source ".env"; fi',
            '```',
            '',
            'Inline code `[[setup.md]]` stays, prose [1](#citation-doc-1) resolves.',
            '',
            '~~~toml',
            '[[bin]]',
            'name = "tool"',
            '~~~'
        ].join('\n')
    );
});

test('duplicate document names are ambiguous: markers degrade, never guess a tile', () => {
    const docs = [
        citation({document: true, title: 'report.pdf', identifier: 'doc-1'}),
        citation({document: true, title: 'Report.PDF', identifier: 'doc-2'})
    ];

    assert.equal(
        rewriteDocumentCitationMarkers('Claim [[report.pdf]] stays unlinked.', docs),
        'Claim [report.pdf] stays unlinked.'
    );
});

test('unmatched markers degrade to plain bracketed names', () => {
    const docs = [citation({document: true, title: 'other.pdf', identifier: 'doc-1'})];

    assert.equal(
        rewriteDocumentCitationMarkers('Claim [[unknown.pdf]] stays readable.', docs),
        'Claim [unknown.pdf] stays readable.'
    );
});

test('while streaming (citations not yet arrived) raw markers show until completion', () => {
    // The MessageBody contract during a live stream: the citations array is
    // empty, so the rewrite is inert — the model's raw text streams through
    // and markers resolve into chips once the citation frames land.
    const live = 'Live view [[Pisa-Studie_ Die Impfung gegen Dummheit _ DIE ZEIT.pdf]] shows raw markers.';

    assert.equal(rewriteDocumentCitationMarkers(live, []), live);
});

test('strings without markers pass through byte-identical (provider messages)', () => {
    const markdown = 'Plain [1](#citation-x) provider chips and *italics* stay as they are.';
    assert.equal(rewriteDocumentCitationMarkers(markdown, [citation({document: true})]), markdown);
});

test('rewrite composes after provider injection without disturbing placed chips', () => {
    // What MessageBody produces: provider injection has already inserted
    // `[1](#citation-web-1)`; document markers are resolved afterwards.
    const citations = [
        citation({title: 'https://web.example', identifier: 'web-1'}),
        citation({document: true, title: 'report.pdf', identifier: 'doc-1'})
    ];
    const injected = 'Web claim [1](#citation-web-1) and doc claim [[report.pdf]].';

    assert.equal(
        rewriteDocumentCitationMarkers(injected, citations),
        'Web claim [1](#citation-web-1) and doc claim [2](#citation-doc-1).'
    );
});

test('marker regex does not span brackets of other markdown constructs', () => {
    const docs = [citation({document: true, title: 'a.pdf', identifier: 'doc-1'})];

    // A markdown link `[text](url)` and an ordered-list `[1]:`-style fragment
    // contain no `[[…]]` token and must survive unchanged.
    const markdown = 'See [link](https://x.test) and [[a.pdf]].';
    assert.equal(
        rewriteDocumentCitationMarkers(markdown, docs),
        'See [link](https://x.test) and [1](#citation-doc-1).'
    );
});

test('chip hrefs use the citation anchor prefix', () => {
    const docs = [citation({document: true, title: 'a.pdf', identifier: 'doc-9'})];
    const result = rewriteDocumentCitationMarkers('[[a.pdf]]', docs);
    assert.ok(result.startsWith(`[1](${CITATION_ANCHOR_PREFIX}doc-9)`), result);
});

test('dedupe merges same-URL citations and their ranges into one tile', () => {
    const merged = dedupeCitations([
        citation({url: 'https://web.example/a', ranges: [[0, 5]], title: 'A'}),
        citation({url: 'https://web.example/a', ranges: [[6, 9]]})
    ], sequencedIdentifier());

    assert.equal(merged.length, 1);
    assert.deepEqual(merged[0].ranges, [[0, 5], [6, 9]]);
    assert.equal(merged[0].title, 'A', 'first title wins, later one fills only if empty');
    assert.equal(merged[0].identifier, 'mount-1', 'one fresh mount-local identifier per tile');
});

test('dedupe keeps URL-less document citations as separate tiles', () => {
    // The bug this guards against: two different documents both carry an
    // empty URL and used to collapse into a single tile.
    const tiles = dedupeCitations([
        citation({document: true, url: '', title: 'a.pdf', identifier: 'server-1'}),
        citation({document: true, url: '', title: 'b.pdf', identifier: 'server-2'}),
        citation({url: 'https://web.example', identifier: 'server-3'})
    ], sequencedIdentifier());

    assert.equal(tiles.length, 3);
    assert.deepEqual(tiles.map(t => t.title), ['a.pdf', 'b.pdf', null]);
    assert.deepEqual(tiles.map(t => t.identifier), ['mount-1', 'mount-2', 'mount-3']);
});

test('dedupe collapses document citations that share a normalized title', () => {
    const tiles = dedupeCitations([
        citation({document: true, url: '', title: 'Report.PDF', identifier: 'server-1', ranges: [[0, 3]]}),
        citation({document: true, url: '', title: ' report.pdf ', identifier: 'server-2', ranges: [[4, 7]]})
    ], sequencedIdentifier());

    assert.equal(tiles.length, 1);
    assert.deepEqual(tiles[0].ranges, [[0, 3], [4, 7]]);
});
