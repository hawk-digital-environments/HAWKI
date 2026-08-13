import {buildQueryString} from '$lib/kernel/api/buildQueryString.js';

export class UriBuilder {
    public readonly API_BASE_URL = '/api/hawki/v1';
    public readonly STORAGE_PROXY_BASE_PATH = '/api/hawki/v1/proxy/storage';

    constructor(
        private readonly baseUri: string
    ) {
    }

    public jsonApiUri(path: string, actionOrId?: string | Record<string, any>): string {
        let uri = this.joinUri(this.baseUri, [this.API_BASE_URL, path]);
        if (actionOrId) {
            if (typeof actionOrId === 'object') {
                const queryString = buildQueryString(actionOrId);
                if (queryString) {
                    uri += queryString;
                }
            } else {
                uri = this.joinUri(uri, actionOrId);
            }
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

    /** URL of the backend web route that terminates the session and redirects to the login page. */
    public logoutUri(): string {
        return this.joinUri(this.baseUri, '/logout');
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
