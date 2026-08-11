import type {UriBuilder} from '$lib/kernel/api/UriBuilder.js';
import type {RestApi} from '$lib/kernel/api/RestApi.js';
import z from 'zod';

const LinkPreviewMetadataSchema = z.object({
    url: z.string(),
    title: z.string().nullable(),
    description: z.string().nullable(),
    image: z.string().nullable(),
    favicon: z.string().nullable(),
    domain: z.string().nullable(),
    isFallback: z.boolean().optional()
});

export type LinkPreviewMetadata = z.infer<typeof LinkPreviewMetadataSchema>;

/**
 * Fetches and caches OpenGraph-style link-preview metadata (title, description,
 * image, favicon, domain) for a URL.
 *
 * The server endpoint is built by `UriBuilder.linkPreviewMetadataUri`; the
 * response is validated against {@link LinkPreviewMetadataSchema}. Results are
 * memoised by URL for the lifetime of the instance, so repeated previews of the
 * same link in a message don't re-fetch. Used by the markdown renderer to
 * render rich link cards inline in chat messages.
 */
export class LinkPreviewApi {
    private readonly metadataCache: Map<string, LinkPreviewMetadata> = new Map();

    constructor(
        private readonly uriBuilder: UriBuilder,
        private readonly restApi: RestApi
    ) {
    }

    public async getLinkPreviewMetadata(url: string) {
        if (this.metadataCache.has(url)) {
            return this.metadataCache.get(url)!;
        }
        const apiUrl = this.uriBuilder.linkPreviewMetadataUri(url);
        const metadata = await this.restApi.fetch(apiUrl, {
            schema: LinkPreviewMetadataSchema
        });
        this.metadataCache.set(url, metadata);
        return metadata;
    }
}
