import type {EnrichedUrlCitation} from '$lib/components/ui/citations/types.js';
import {CITATION_ANCHOR_PREFIX} from '$plugins/core/modules/chat/components/message/injectCitationsIntoMarkdown.js';

/**
 * Inline document-reference markers the knowledge-tool prompt asks the model
 * to emit: `[[document name.pdf]]`, where the name is taken verbatim from the
 * RAG tool result's `documents` list. {@link rewriteDocumentCitationMarkers}
 * resolves them against the message's document citations; anything the
 * renderer cannot resolve degrades to plain `[name]` text.
 */
const DOCUMENT_MARKER_REGEX = /\[\[([^\[\]]+)\]\]/g;

/**
 * Normalizes a document name for marker/tile matching: trims, collapses
 * whitespace, folds case. Both sides (the marker name from the tool result's
 * `documents` list and the tile title) are canonicalised to the attachment
 * name server-side, so a plain equality up to trivial variance suffices —
 * any structural name drift degrades to a plain `[name]` instead of risking
 * a wrong-source link.
 */
export function normalizeDocumentName(name: string): string {
    return name
        .trim()
        .replace(/\s+/g, ' ')
        .toLowerCase();
}

/**
 * Rewrites `[[document name]]` markers into the same numbered citation chips
 * the provider-citation path uses (`[N](#citation-<identifier>)`, rendered by
 * `ExtendedLinkNode.svelte`, scrolling to the matching `Citation` tile).
 *
 * Markers are matched against the message's document citations
 * (`document === true`) by normalized title — the backend canonicalises both
 * the marker name (the tool result's `documents` list) and the tile title to
 * the attachment's name before either reaches the client, so the names agree
 * by construction. Chip numbers follow the citation's index in `citations`, the
 * same array the tiles render, keeping document chips, web-source chips and
 * tiles consistent. Unmatched markers degrade to plain `[name]`; they never
 * render raw `[[…]]`.
 *
 * Run AFTER `injectCitationsIntoMarkdown`: that function places its markers
 * by offsets into the original string, so it must see the unmodified input.
 * Provider citations (offset ranges) are untouched — this pass only consumes
 * `[[…]]` tokens, which provider messages do not contain.
 */
export function rewriteDocumentCitationMarkers(
    markdown: string,
    citations: Array<EnrichedUrlCitation>
): string {
    if (!markdown.includes('[[')) {
        return markdown;
    }

    const documentIndexByName = new Map<string, number>();
    citations.forEach((citation, index) => {
        if (citation.document !== true) {
            return;
        }
        const key = normalizeDocumentName(citation.title ?? '');
        if (key === '' || documentIndexByName.has(key)) {
            return;
        }
        documentIndexByName.set(key, index);
    });

    return markdown.replace(DOCUMENT_MARKER_REGEX, (_, rawName: string) => {
        const name = String(rawName).trim();
        const index = documentIndexByName.get(normalizeDocumentName(name));

        if (index === undefined) {
            return `[${name}]`;
        }

        return `[${index + 1}](${CITATION_ANCHOR_PREFIX}${citations[index].identifier})`;
    });
}
