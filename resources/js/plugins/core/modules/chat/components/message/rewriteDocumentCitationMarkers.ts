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
 * Code segments the marker rewrite must never touch: fenced blocks (``` or
 * ~~~, an unterminated fence protects the rest of the string) and inline
 * backtick spans. Bash conditionals (`[[ -f "$x" ]]`) and TOML array-of-table
 * headers (`[[package]]`) are content, not citations — rewriting them would
 * change the code's meaning.
 */
const CODE_SEGMENT_REGEX =
    /(```[\s\S]*?(?:```|$))|(~~~[\s\S]*?(?:~~~|$))|(``[^`]*``|`[^`\n]*`)/g;

/**
 * Normalizes a document name for marker/tile matching: trims, collapses
 * whitespace, folds case. Both sides (the marker name from the tool result's
 * `documents` list and the tile title) are canonicalised to the attachment
 * name server-side, so a plain equality up to trivial variance suffices —
 * anything else degrades to a plain `[name]` instead of risking a
 * wrong-source link. (A fuzzy-similarity tier for garbled names was
 * deliberately left out while the citeId contract is validated live; the
 * citeId makes the model echo a two-character token instead of copying a
 * long name, which is the failure the fuzzy tier would paper over.)
 */
export function normalizeDocumentName(name: string): string {
    return name
        .trim()
        .replace(/\s+/g, ' ')
        .toLowerCase();
}

/**
 * Rewrites `[[document name]]` / `[[D1]]` markers into the same numbered
 * citation chips the provider-citation path uses (`[N](#citation-<identifier>)`,
 * rendered by `ExtendedLinkNode.svelte`, scrolling to the matching `Citation`
 * tile).
 *
 * The rewrite only runs for knowledge answers — messages that carry at least
 * one document citation (`document === true`). Everything else passes through
 * byte-identical, so `[[…]]` tokens in plain chat or other models' code are
 * never touched; during a live stream the citations array is still empty, so
 * raw markers show until the stream completes and resolve into chips.
 *
 * Markers resolve against the message's document citations in two tiers,
 * most reliable first:
 *
 * 1. **citeId** — the run-stable `D1`, `D2`, … handle the knowledge tool
 *    injects into its result's `documents` list; the model just echoes it.
 * 2. **exact name** — normalized title equality, the pre-citeId contract;
 *    still what older recorded messages carry.
 *
 * A name claimed by more than one document citation is ambiguous and left
 * unresolved: the model cannot distinguish duplicate attachment names
 * either, so those markers degrade rather than risk linking to a guessed
 * tile. Chip numbers follow the citation's index in `citations`, the same
 * array the tiles render, keeping document chips, web-source chips and
 * tiles consistent. Unmatched markers degrade to plain `[name]`.
 *
 * Markers inside fenced code blocks or inline code spans are never rewritten
 * — code is content, not citation syntax.
 *
 * Run AFTER `injectCitationsIntoMarkdown`: that function places its markers
 * by offsets into the original string, so it must see the unmodified input.
 * Provider citations (offset ranges) are untouched — this pass only consumes
 * `[[…]]` tokens outside code, which provider messages do not contain.
 */
export function rewriteDocumentCitationMarkers(
    markdown: string,
    citations: Array<EnrichedUrlCitation>
): string {
    // Knowledge answers only: without a document citation there is nothing
    // to resolve or degrade, so any `[[…]]` the text contains (other
    // models' code, plain chat) passes through untouched.
    if (!citations.some(citation => citation.document === true) || !markdown.includes('[[')) {
        return markdown;
    }

    const documentIndexByName = new Map<string, number>();
    const documentIndexByCiteId = new Map<string, number>();
    const ambiguousNames = new Set<string>();
    const ambiguousCiteIds = new Set<string>();
    citations.forEach((citation, index) => {
        if (citation.document !== true) {
            return;
        }

        const nameKey = normalizeDocumentName(citation.title ?? '');
        if (nameKey !== '') {
            if (documentIndexByName.has(nameKey)) {
                // Two attachments share the name — a marker cannot say which
                // tile is meant, so it must not link to either.
                ambiguousNames.add(nameKey);
            } else {
                documentIndexByName.set(nameKey, index);
            }
        }

        if (typeof citation.citeId === 'string' && citation.citeId.trim() !== '') {
            const citeKey = normalizeDocumentName(citation.citeId);
            if (documentIndexByCiteId.has(citeKey)) {
                ambiguousCiteIds.add(citeKey);
            } else {
                documentIndexByCiteId.set(citeKey, index);
            }
        }
    });
    for (const key of ambiguousNames) {
        documentIndexByName.delete(key);
    }
    for (const key of ambiguousCiteIds) {
        documentIndexByCiteId.delete(key);
    }

    const resolveMarker = (rawName: string): number | undefined => {
        const normalized = normalizeDocumentName(String(rawName).trim());

        return documentIndexByCiteId.get(normalized)
            ?? documentIndexByName.get(normalized);
    };

    const rewriteProse = (prose: string): string =>
        prose.replace(DOCUMENT_MARKER_REGEX, (_, rawName: string) => {
            const name = String(rawName).trim();
            const index = resolveMarker(name);

            if (index === undefined) {
                return `[${name}]`;
            }

            return `[${index + 1}](${CITATION_ANCHOR_PREFIX}${citations[index].identifier})`;
        });

    let result = '';
    let lastCopied = 0;
    for (const match of markdown.matchAll(CODE_SEGMENT_REGEX)) {
        result += rewriteProse(markdown.slice(lastCopied, match.index));
        result += match[0];
        lastCopied = match.index + match[0].length;
    }

    return result + rewriteProse(markdown.slice(lastCopied));
}
