import assert from 'node:assert/strict';
import {test} from 'node:test';
import {normalizeDocumentName, rewriteDocumentCitationMarkers} from '../../../resources/js/plugins/core/modules/chat/components/message/rewriteDocumentCitationMarkers.js';
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

test('web citations never match markers — provider path stays untouched', () => {
    const web = [citation({title: 'report.pdf', identifier: 'web-1', document: false})];

    assert.equal(
        rewriteDocumentCitationMarkers('Claim [[report.pdf]].', web),
        'Claim [report.pdf].'
    );
});

test('unmatched markers degrade to plain bracketed names', () => {
    const docs = [citation({document: true, title: 'other.pdf', identifier: 'doc-1'})];

    assert.equal(
        rewriteDocumentCitationMarkers('Claim [[unknown.pdf]] stays readable.', docs),
        'Claim [unknown.pdf] stays readable.'
    );
    // No citations at all: still no raw double brackets.
    assert.equal(
        rewriteDocumentCitationMarkers('Lost [[report.pdf]] citations.', []),
        'Lost [report.pdf] citations.'
    );
});

test('while streaming (citations not yet arrived) tokens degrade, not show raw', () => {
    // The MessageBody contract during a live stream: injection is skipped,
    // the rewrite still runs with the (empty) citations known so far.
    assert.equal(
        rewriteDocumentCitationMarkers('Live view [[Pisa-Studie_ Die Impfung gegen Dummheit _ DIE ZEIT.pdf]] shows clean names.', []),
        'Live view [Pisa-Studie_ Die Impfung gegen Dummheit _ DIE ZEIT.pdf] shows clean names.'
    );
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
