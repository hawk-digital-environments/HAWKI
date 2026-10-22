/** Human-readable file size (`512 B`, `12.3 KB`, `4.0 MB`); empty for an unknown size. */
export function formatFileSize(bytes?: number | null): string {
    if (bytes === undefined || bytes === null) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
