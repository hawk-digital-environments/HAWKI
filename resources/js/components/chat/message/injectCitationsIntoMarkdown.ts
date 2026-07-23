import type {EnrichedUrlCitation} from '$lib/components/ui/citations/types.js';

/**
 * Hrefs of injected inline citation links start with this prefix, followed by
 * the citation's `identifier`. The citation tile rendered by
 * `MessageCitation.svelte` carries the matching id (without the `#`), so the
 * inline marker scrolls to its tile.
 */
export const CITATION_ANCHOR_PREFIX = '#citation-';

/**
 * Returns the DOM id of the citation tile the inline markers link to.
 */
export function citationAnchorId(identifier: string): string {
    return CITATION_ANCHOR_PREFIX.slice(1) + identifier;
}

/**
 * Returns the citation identifier from an inline marker's href, or null if the
 * href does not start with the expected prefix.
 * @param anchorId
 */
export function citationIdFromAnchorId(anchorId: string): string | null {
    if (!anchorId.startsWith(CITATION_ANCHOR_PREFIX.slice(0))) {
        console.warn('Failed to parse citation identifier from anchor id', anchorId);
        return null;
    }
    return anchorId.slice(CITATION_ANCHOR_PREFIX.length);
}

// Matches an optional-paren-wrapped markdown link at the end of a string,
// e.g. "([display text](https://...))" or "[display](url)". Some providers
// (OpenAI) inject their own links at citation positions; we strip those and
// replace them with our own markers.
const TRAILING_LINK_REGEX = /\(?\[[^\]]*\]\([^)]*\)\)?\s*$/;

/**
 * Injects inline citation markers into a markdown string. For every citation
 * range end a markdown link of the form `[N](#citation-<identifier>)` is
 * inserted, which `ExtendedLinkNode.svelte` renders as a small numbered chip
 * scrolling to the corresponding source tile. Positions inside fenced code
 * blocks are skipped.
 */
export function injectCitationsIntoMarkdown(
    markdown: string,
    citations: Array<EnrichedUrlCitation>
): string {
    if (!Array.isArray(citations) || citations.length === 0) {
        return markdown;
    }

    // Google reports UTF-8 byte offsets, OpenAI character offsets. The flag
    // is carried per citation; when missing we default to byte offsets.
    const useByteOffsets = citations[0]?.byteOffset !== false;
    const contentBytes = useByteOffsets ? new TextEncoder().encode(markdown) : null;
    const toCharOffset = useByteOffsets
        ? (offset: number) => new TextDecoder().decode(contentBytes!.slice(0, offset)).length
        : (offset: number) => offset;

    // char offset -> set of citation indices ending there
    const markersByOffset = new Map<number, Set<number>>();
    citations.forEach((citation, index) => {
        for (const range of citation.ranges ?? []) {
            const end = range[1];
            if (end == null) {
                continue;
            }
            const charOffset = toCharOffset(end);
            let indices = markersByOffset.get(charOffset);
            if (!indices) {
                indices = new Set();
                markersByOffset.set(charOffset, indices);
            }
            indices.add(index);
        }
    });

    const codeBlockRegex = /```[\s\S]*?```/g;
    const codeRanges: Array<{ start: number; end: number }> = [];
    let match;
    while ((match = codeBlockRegex.exec(markdown)) !== null) {
        codeRanges.push({start: match.index, end: codeBlockRegex.lastIndex});
    }
    const isInCodeBlock = (index: number) =>
        codeRanges.some(range => index >= range.start && index <= range.end);

    // Insert from the end to the start so earlier offsets stay valid.
    const insertionPoints = Array.from(markersByOffset.entries())
        .filter(([offset]) => !isInCodeBlock(offset))
        .sort((a, b) => b[0] - a[0]);

    let result = markdown;
    for (const [offset, indices] of insertionPoints) {
        const markers = Array.from(indices)
            .sort((a, b) => a - b)
            .map(index => `[${index + 1}](${CITATION_ANCHOR_PREFIX}${citations[index].identifier})`)
            .join('');

        const prefix = result.slice(0, offset).replace(TRAILING_LINK_REGEX, '');
        result = prefix + markers + result.slice(offset);
    }

    return result;
}
