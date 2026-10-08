import type {EnrichedUrlCitation} from '$lib/components/ui/citations/types.js';
import {normalizeDocumentName} from '$plugins/core/modules/chat/components/message/rewriteDocumentCitationMarkers.js';

/**
 * Collapses the server-provided citation list into the render list: one
 * entry per distinct source, each with a fresh mount-local `identifier` that
 * chips and its tile share.
 *
 * Providers may report the same URL more than once (one entry per cited text
 * segment) — those merge by URL, combining their ranges so every cited
 * segment still gets its inline marker. Document citations have no URL
 * (resolved attachments use a proxy URL, unresolved ones an empty string);
 * they dedupe by normalized document title instead, so two DIFFERENT
 * documents never collapse into one tile just because both lack a URL.
 *
 * @param makeIdentifier mints the mount-local identifier chips and tiles
 *                       share (caller supplies the component-scoped source).
 */
export function dedupeCitations(
    citations: Array<EnrichedUrlCitation>,
    makeIdentifier: () => string
): Array<EnrichedUrlCitation> {
    const bySource = new Map<string, EnrichedUrlCitation>();

    for (const citation of citations) {
        const key = citation.document === true
            ? 'document:' + normalizeDocumentName(citation.title ?? '')
            : citation.url;

        const existing = bySource.get(key);

        if (!existing) {
            bySource.set(key, {
                ...citation,
                identifier: makeIdentifier()
            });
            continue;
        }

        existing.ranges = [...(existing.ranges ?? []), ...(citation.ranges ?? [])];
        if (!existing.title && citation.title) {
            existing.title = citation.title;
        }
    }

    return [...bySource.values()];
}
