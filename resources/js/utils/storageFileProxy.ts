import {getHawkiApp} from '$lib/legacy/legacy.js';

/**
 * Builds the proxied URL for a stored file so the browser can fetch it through
 * the HAWKI backend rather than hitting the storage provider directly.
 *
 * Returns `null` when `fileIdentifier` is falsy (e.g. a message without an attachment).
 *
 * @example
 * const url = buildStorageFileUrl(attachment.file_identifier);
 * if (url) { img.src = url; }
 * @deprecated use {@link UriBuilder.storageFileUri} instead
 */
export function buildStorageFileUrl(fileIdentifier: string | null): string | null {
    return getHawkiApp().uriBuilder.storageFileUri(fileIdentifier);
}
