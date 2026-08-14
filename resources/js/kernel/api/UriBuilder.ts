import {buildQueryString} from '$lib/kernel/api/buildQueryString.js';

export class UriBuilder {
    public readonly API_BASE_URL = '/api/hawki/v1';
    public readonly STORAGE_PROXY_BASE_PATH = '/api/hawki/v1/proxy/storage';

    constructor(
        private readonly baseUri: string
    ) {
    }

    public jsonApiUri(path: string, actionIdOrQuery?: string | number, queryParams?: Record<string, any>): string;
    public jsonApiUri(path: string, actionIdOrQuery?: Record<string, any>): string;
    public jsonApiUri(
        path: string,
        actionIdOrQuery?: string | number | Record<string, any>,
        queryParams?: Record<string, any>
    ): string {
        let uri = this.joinUri(this.baseUri, [this.API_BASE_URL, path]);

        let actionOrId: string | null = null;
        let query: Record<string, any> | undefined = queryParams;

        if (typeof actionIdOrQuery === 'string' || typeof actionIdOrQuery === 'number') {
            actionOrId = actionIdOrQuery.toString();
        } else if (actionIdOrQuery && typeof actionIdOrQuery === 'object') {
            if (query) {
                throw new Error('Cannot provide queryParams when actionIdOrQuery is an object; use only one or the other.');
            }
            query = actionIdOrQuery;
        }

        if (actionOrId) {
            uri = this.joinUri(uri, actionOrId);
        }

        if (query) {
            uri += buildQueryString(query);
        }

        return uri;
    }
    /**
     * Builds the proxied URL for a stored file so the browser can fetch it through
     * the HAWKI backend rather than hitting the storage provider directly.
     *
     * Returns `null` when `fileIdentifier` is falsy (e.g. a message without an attachment).
     */
    public storageFileUri(fileIdentifier: string | null): string | null {
        if (!fileIdentifier) {
            return null;
        }

        return this.joinUri(this.baseUri, [this.STORAGE_PROXY_BASE_PATH, encodeURIComponent(fileIdentifier)]);
    }

    public linkPreviewMetadataUri(url: string): string {
        return this.jsonApiUri('/proxy/link-preview/metadata', {url});
    }

    public linkPreviewFaviconUri(url: string): string {
        return this.jsonApiUri('/proxy/link-preview/favicon', {url});
    }

    private joinUri(base: string, paths: string | string[]): string {
        if (!Array.isArray(paths)) {
            paths = [paths];
        }
        const joinedPath = paths.map(p => p.replace(/^\/+|\/+$/g, '')).join('/');
        return base.replace(/\/+$/g, '') + '/' + joinedPath;
    }
}
