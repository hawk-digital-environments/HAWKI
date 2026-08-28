/**
 * Triggers a browser download of `blob` under `filename` via a temporary
 * anchor; the object URL is revoked right after the click is dispatched.
 */
export function downloadBlob(filename: string, blob: Blob): void {
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.download = filename;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url));
}

/** Downloads `content` as a text file of the given MIME `type`. */
export function downloadText(filename: string, content: string, type: string): void {
    downloadBlob(filename, new Blob([content], {type}));
}
