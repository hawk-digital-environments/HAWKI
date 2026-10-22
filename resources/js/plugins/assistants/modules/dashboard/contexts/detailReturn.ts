/**
 * Where an assistant's detail page should return to when its back button is
 * pressed: the dashboard page the assistant was opened from.
 *
 * Remembered rather than delegated to `history.back()`, because the newest
 * history entry is not always the page the user came from — after a round
 * trip through the builder it is the builder.
 *
 * Plain module state, so a direct URL or a reload starts without an origin
 * and the detail page falls back to the store.
 */
let returnPath: string | null = null;

/** Records the page the detail page is being opened from. */
export function rememberDetailReturnPath(path: string): void {
    returnPath = path;
}

/** The remembered origin page, or null when the detail page was entered without one. */
export function detailReturnPath(): string | null {
    return returnPath;
}
