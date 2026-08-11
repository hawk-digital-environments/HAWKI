/**
 * A single web source an AI response cited, as reported by the model
 * provider. This is the raw shape received from the backend/provider — it
 * has no stable per-render identifier yet, which is what
 * {@link EnrichedUrlCitation} adds.
 *
 * Consumed by `injectCitationsIntoMarkdown` (which inserts inline `[N]`
 * markers into the message markdown at `ranges` end offsets) and rendered as
 * source tiles by `Citation.svelte` inside a `CitationList`.
 */
export interface UrlCitation {
    /** The URL of the cited source, opened in a new tab from the `Citation` tile. */
    url: string;
    /** Page title of the cited source, or null if the provider didn't supply one. */
    title: string | null;
    /**
     * Start/end offset pairs into the message text that this citation covers.
     * Only the end offset of each range is currently used, to place an inline
     * `[N]` marker right after the cited text. Offsets are measured per
     * {@link byteOffset}.
     */
    ranges: Array<[number, number]>;
    /** Optional provider-reported start offset of the cited span (character or byte, see {@link byteOffset}). Not currently used for marker placement. */
    startIndex?: number;
    /** Optional provider-reported end offset of the cited span (character or byte, see {@link byteOffset}). Not currently used for marker placement. */
    endIndex?: number;
    /**
     * Providers differ in how the range offsets are measured: Google uses
     * UTF-8 byte offsets, OpenAI uses character offsets. When this flag is
     * missing, byte offsets are assumed.
     */
    byteOffset?: boolean;
}

/**
 * A {@link UrlCitation} enriched with a stable `identifier`, generated once
 * per citation URL by `MessageBody.svelte` (via `$props.id()` + a random
 * suffix, cached per URL for the lifetime of the message). The identifier
 * links two independently rendered pieces of UI together:
 *
 * - the inline `[N]` marker inserted into the markdown, rendered as a
 *   `CitationReference` chip with `href="#citation-<identifier>"`
 * - the matching source tile rendered by `Citation.svelte` inside a
 *   `CitationList`, which carries `identifier` as its DOM id
 *
 * Clicking the chip calls `CitationContext.focusCitation(identifier)`, which
 * scrolls to and flashes the tile with the same identifier.
 */
export interface EnrichedUrlCitation extends UrlCitation {
    /** Stable per-render id shared between the inline citation chip and its source tile. */
    identifier: string;
}
