/**
 * Pure string helpers for building/parsing the inline citation anchor markers
 * that `injectCitationsIntoMarkdown` (host app, chat domain) writes into a
 * message's markdown body, and that `ExtendedLinkNode`/`CitationReference`
 * (this package) read back to render a `CitationReference` chip.
 */

/**
 * Hrefs of injected inline citation links start with this prefix, followed by
 * the citation's `identifier`. `ExtendedLinkNode.svelte` recognizes this
 * prefix and renders a `CitationReference` chip in its place; clicking the
 * chip calls `CitationContext.focusCitation(identifier)` to scroll/flash the
 * matching `Citation` tile — there is no real DOM anchor being navigated to.
 */
export const CITATION_ANCHOR_PREFIX = '#citation-';

/**
 * Href for a `CitationReference` chip's own link element. Distinct from the
 * markers `injectCitationsIntoMarkdown` writes into the markdown body (which
 * use `CITATION_ANCHOR_PREFIX` directly) — this one is never parsed back via
 * `citationIdFromAnchorId`, only ever clicked and intercepted.
 */
export function citationAnchorId(identifier: string): string {
    return CITATION_ANCHOR_PREFIX.slice(1) + identifier;
}

/**
 * Returns the citation identifier from an inline marker's href, or null if the
 * href does not start with the expected prefix.
 */
export function citationIdFromAnchorId(anchorId: string): string | null {
    if (!anchorId.startsWith(CITATION_ANCHOR_PREFIX.slice(0))) {
        console.warn('Failed to parse citation identifier from anchor id', anchorId);
        return null;
    }
    return anchorId.slice(CITATION_ANCHOR_PREFIX.length);
}
