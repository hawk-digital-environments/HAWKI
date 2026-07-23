import z from 'zod';
import {buildApiUrl, fetchApi} from '$lib/data/api/api.js';

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

const metadataCache = new Map<string, Promise<LinkPreviewMetadata>>();

/**
 * Fetches preview metadata (title, description, image, …) for the given URL
 * through the backend proxy. Results are cached for the lifetime of the page,
 * so hovering the same link twice only triggers a single request.
 */
export function fetchLinkPreviewMetadata(url: string): Promise<LinkPreviewMetadata> {
    let promise = metadataCache.get(url);
    if (!promise) {
        const apiUrl = buildApiUrl('/proxy/link-preview/metadata', {url});
        promise = fetchApi(apiUrl, {
            schema: LinkPreviewMetadataSchema
        });
        promise.catch(() => metadataCache.delete(url));
        metadataCache.set(url, promise);
    }
    return promise;
}

/**
 * Builds the URL of the favicon proxy endpoint for the given link. The proxy
 * fetches the icon server-side so the user's browser never talks to Google
 * directly. Safe to use as a plain `<img src>`.
 */
export function buildLinkPreviewFaviconUrl(url: string): string {
    return buildApiUrl('/proxy/link-preview/favicon', {url});
}
