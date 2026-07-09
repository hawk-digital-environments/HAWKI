import {getConfig} from '$lib/data/config/config.js';

const STORAGE_PROXY_BASE_PATH = '/api/hawki/v1/proxy/storage/';

/**
 * Builds the proxied URL for a stored file so the browser can fetch it through
 * the HAWKI backend rather than hitting the storage provider directly.
 *
 * Returns `null` when `fileIdentifier` is falsy (e.g. a message without an attachment).
 *
 * @example
 * const url = buildStorageFileUrl(attachment.file_identifier);
 * if (url) { img.src = url; }
 */
export function buildStorageFileUrl(fileIdentifier: string | null): string | null {
    if (!fileIdentifier) {
        return null;
    }

    const baseUrl = (getConfig().transfer.baseUrl).replace(/\/+$/, '');
    return `${baseUrl}${STORAGE_PROXY_BASE_PATH}${encodeURIComponent(fileIdentifier)}`;
}
